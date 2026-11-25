<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\ExperienceController;
use App\Http\Requests\Admin\Concerns\PreparesSlug;
use App\Models\Addon;
use App\Models\Experience;
use App\Rules\PlainText;
use App\Rules\SafeUpload;
use App\Support\IconNames;
use App\Support\StartTimes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add or edit an experience (Admin → Experiences, PLAN 3.4.2). "What's included" is one item per line
 * (`includes_text`). User decisions 2026-11-25: the experience is free and has no trip length of its own (the
 * guest books the boat by the hour); it departs inside a window — `departs_from` … `departs_until` on the boats'
 * start-time grid — saved as every half hour of it (`time_slots`). `extras` are the slugs of add-ons suggested
 * with it at checkout (optional; Admin → Add-ons, not in the Trash), saved in the order ticked.
 */
class ExperienceRequest extends FormRequest
{
    use PreparesSlug;

    public function authorize(): bool
    {
        $experience = $this->route('experience');

        return $experience instanceof Experience
            ? $this->user()->can('update', $experience)
            : $this->user()->can('create', Experience::class);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareSlug('name');

        $this->merge(['includes' => $this->lines('includes_text')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $experience = $this->route('experience');

        return [
            'name' => ['required', 'string', 'max:120', new PlainText],
            'slug' => ['required', 'string', 'max:80', self::SLUG_RULE, Rule::unique('experiences', 'slug')->ignore($experience instanceof Experience ? $experience->getKey() : null)],
            'category' => ['required', 'string', Rule::in(array_keys($this->categories()))],
            'icon' => ['required', 'string', Rule::in(IconNames::PICKER)],
            'tagline' => ['nullable', 'string', 'max:255', new PlainText],
            'description' => ['nullable', 'string', 'max:5000', new PlainText],
            'includes_text' => ['nullable', 'string', 'max:3000'],
            'includes' => ['array', 'max:12'],
            'includes.*' => ['string', 'max:120', 'distinct', new PlainText],
            // The departure window, on the boats' start-time grid (4.2): 05:00 … 22:00, every half hour.
            'departs_from' => ['required', 'string', Rule::in(StartTimes::grid())],
            'departs_until' => ['required', 'string', Rule::in(StartTimes::grid())],
            'weekdays' => ['nullable', 'array', 'max:7'],
            'weekdays.*' => ['integer', 'distinct', 'between:0,6'],
            'season_starts_on' => ['nullable', 'required_with:season_ends_on', 'date_format:Y-m-d'],
            'season_ends_on' => ['nullable', 'required_with:season_starts_on', 'date_format:Y-m-d', 'after_or_equal:season_starts_on'],
            'extras' => ['nullable', 'array', 'max:12'],
            'extras.*' => ['string', 'max:80', 'distinct', Rule::exists('addons', 'slug')->whereNull('deleted_at')],
            'is_active' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'sort' => ['required', 'integer', 'min:0', 'max:9999'],
            'seo_title' => ['nullable', 'string', 'max:120', new PlainText],
            'seo_description' => ['nullable', 'string', 'max:255', new PlainText],
            'image' => ['nullable', SafeUpload::image()],
            'remove_image' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->slugMessages('experience') + [
            'departs_from.required' => 'Choose the first departure time.',
            'departs_until.required' => 'Choose the last departure time.',
            'departs_from.in' => 'Departures are on the hour or half hour, from 5:00 AM to 10:00 PM (the times boats depart at).',
            'departs_until.in' => 'Departures are on the hour or half hour, from 5:00 AM to 10:00 PM (the times boats depart at).',
            'includes.max' => 'List at most 12 included items.',
            'includes.*.max' => 'Each included item can be at most 120 characters.',
            'season_ends_on.after_or_equal' => 'The season must end on or after its first day.',
            'season_starts_on.required_with' => 'Enter both season dates, or neither.',
            'season_ends_on.required_with' => 'Enter both season dates, or neither.',
            'extras.max' => 'Pick at most 12 extras.',
            'extras.*.exists' => 'One of the extras is no longer offered. Reload the page and try again.',
        ];
    }

    /**
     * The last departure can't come before the first.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $from = $this->input('departs_from');
                $until = $this->input('departs_until');
                if (! $validator->errors()->hasAny(['departs_from', 'departs_until']) && is_string($from) && is_string($until)
                    && StartTimes::minutes($until) < StartTimes::minutes($from)) {
                    $validator->errors()->add('departs_until', 'The last departure must be at or after the first one.');
                }
            },
        ];
    }

    /** @return array<string, string> */
    public function categories(): array
    {
        return collect(ExperienceController::CATEGORIES)->except('all')->all();
    }

    /**
     * The fields saved on the experience.
     *
     * @return array<string, mixed>
     */
    public function experienceData(): array
    {
        $data = $this->safe()->except(['includes_text', 'departs_from', 'departs_until', 'weekdays', 'extras', 'image', 'remove_image']);
        $weekdays = array_map('intval', $this->validated('weekdays') ?? []);
        sort($weekdays);

        return array_merge($data, [
            'time_slots' => StartTimes::between($this->validated('departs_from'), $this->validated('departs_until')),
            'includes' => $data['includes'] === [] ? null : $data['includes'],
            'allowed_weekdays' => $weekdays === [] || count($weekdays) === 7 ? null : $weekdays, // null = every day
        ]);
    }

    /**
     * The suggested extras as add-on id => pivot (their order), for the `extras` sync.
     *
     * @return array<int, array{sort: int}>
     */
    public function extras(): array
    {
        $slugs = array_values($this->validated('extras') ?? []);
        $ids = Addon::query()->whereIn('slug', $slugs)->pluck('id', 'slug');

        return collect($slugs)->mapWithKeys(fn (string $slug, int $sort) => [$ids[$slug] => ['sort' => $sort]])->all();
    }
}
