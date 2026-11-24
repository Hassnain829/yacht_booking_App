<?php

namespace App\Http\Requests\Admin;

use App\Http\Controllers\ExperienceController;
use App\Http\Requests\Admin\Concerns\PreparesSlug;
use App\Models\Experience;
use App\Rules\PlainText;
use App\Rules\SafeUpload;
use App\Support\IconNames;
use App\Support\Money;
use App\Support\StartTimes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add or edit an experience (Admin → Experiences, PLAN 3.4.2). "What's included" is one item per line and the
 * start times a comma-separated list; both arrive as text (`includes_text`, `time_slots_text`) and are checked
 * as lists. The package fee is typed in dollars and saved in cents.
 */
class ExperienceRequest extends FormRequest
{
    use PreparesSlug;

    public const DURATIONS = [1, 2, 3, 4, 5, 6, 8, 10, 12];

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

        $slots = is_string($this->input('time_slots_text')) ? preg_split('/[\s,;]+/', trim($this->input('time_slots_text'))) : [];
        $this->merge([
            'includes' => $this->lines('includes_text'),
            'time_slots' => array_values(array_filter($slots ?: [], fn (string $slot) => $slot !== '')),
        ]);
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
            'durations' => ['required', 'array', 'min:1', 'max:'.count(self::DURATIONS)],
            'durations.*' => ['integer', 'distinct', Rule::in(self::DURATIONS)],
            'time_slots_text' => ['nullable', 'string', 'max:400'],
            'time_slots' => ['required', 'array', 'min:1', 'max:24'],
            'time_slots.*' => ['date_format:H:i', 'distinct', Rule::in(StartTimes::grid())], // the boats' start-time grid (4.2)
            'weekdays' => ['nullable', 'array', 'max:7'],
            'weekdays.*' => ['integer', 'distinct', 'between:0,6'],
            'season_starts_on' => ['nullable', 'required_with:season_ends_on', 'date_format:Y-m-d'],
            'season_ends_on' => ['nullable', 'required_with:season_starts_on', 'date_format:Y-m-d', 'after_or_equal:season_starts_on'],
            'package_fee' => ['required', 'numeric', 'min:0', 'max:100000', 'decimal:0,2'],
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
            'durations.required' => 'Choose at least one trip length.',
            'time_slots.required' => 'Enter at least one start time, e.g. 17:30.',
            'time_slots.*.date_format' => 'Start times use the 24-hour format, e.g. 09:00 or 17:30.',
            'time_slots.*.distinct' => 'Each start time can be listed once.',
            'time_slots.*.in' => 'Start times are on the hour or half hour, from 05:00 to 22:00 (the times boats depart at).',
            'includes.max' => 'List at most 12 included items.',
            'includes.*.max' => 'Each included item can be at most 120 characters.',
            'season_ends_on.after_or_equal' => 'The season must end on or after its first day.',
            'season_starts_on.required_with' => 'Enter both season dates, or neither.',
            'season_ends_on.required_with' => 'Enter both season dates, or neither.',
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
        $data = $this->safe()->except(['includes_text', 'time_slots_text', 'weekdays', 'package_fee', 'image', 'remove_image']);
        $durations = array_map('intval', $data['durations']);
        sort($durations);
        $slots = $data['time_slots'];
        sort($slots);
        $weekdays = array_map('intval', $this->validated('weekdays') ?? []);
        sort($weekdays);

        return array_merge($data, [
            'durations' => $durations,
            'time_slots' => $slots,
            'includes' => $data['includes'] === [] ? null : $data['includes'],
            'allowed_weekdays' => $weekdays === [] || count($weekdays) === 7 ? null : $weekdays, // null = every day
            'package_fee_cents' => Money::toCents($this->validated('package_fee')),
        ]);
    }
}
