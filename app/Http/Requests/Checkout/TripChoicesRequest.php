<?php

namespace App\Http\Requests\Checkout;

use App\Models\Addon;
use App\Models\Boat;
use App\Models\Experience;
use App\Services\BoatQuotes;
use App\Support\Pricing\QuoteRequest;
use App\Support\StartTimes;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The trip the boat page's booking card sends to checkout (PLAN 4.5): yacht, optional experience, date, start
 * time, length, guests and optional add-ons (a pricing package's, comma-separated or a list). Checked here for
 * shape; whether the boat can take it is AvailabilityService's answer when the trip is held. Wrong input goes back
 * to the boat's card with the message (never echoed unescaped).
 */
class TripChoicesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $addons = $this->input('addons');
        if (is_string($addons)) {
            $this->merge(['addons' => $addons === '' ? [] : explode(',', $addons)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $today = CarbonImmutable::now(config('app.display_timezone'));

        return [
            'yacht' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'experience' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$today->addMonths(18)->toDateString()],
            'time' => ['required', 'string', Rule::in(StartTimes::grid())],
            'hours' => ['required', 'integer', 'between:1,24'],
            'guests' => ['required', 'integer', 'between:1,500'],
            'addons' => ['nullable', 'array', 'max:10'],
            'addons.*' => ['string', 'max:80', 'distinct', Rule::exists('addons', 'slug')->where('is_active', true)->whereNull('deleted_at')],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'date.required' => 'Pick a date for your trip.',
            'time.required' => 'Pick a start time for your trip.',
            'time.in' => 'Pick one of the start times shown.',
            'addons.*.exists' => 'One of the add-ons is no longer offered.',
        ];
    }

    /**
     * The trip, with the boat as AvailabilityService and QuoteService read it.
     *
     * @return array{boat: Boat, experience: ?Experience, startsAt: CarbonImmutable, hours: int, guests: int, addons: list<Addon>}
     *
     * @throws ValidationException when the boat or the experience can't be booked
     */
    public function trip(BoatQuotes $boats): array
    {
        $boat = $boats->find((string) $this->validated('yacht'));
        if ($boat === null || ! $boat->isBookable()) {
            throw ValidationException::withMessages(['yacht' => 'This yacht is not taking bookings right now.']);
        }
        $slug = $this->validated('experience');
        $experience = $slug ? $boat->experiences->firstWhere('slug', $slug) : null;
        if ($slug && ($experience === null || $boat->offering($experience) === null)) {
            throw ValidationException::withMessages(['experience' => 'This yacht doesn\'t offer that experience.']);
        }
        $slugs = array_values($this->validated('addons') ?? []);
        $addons = Addon::query()->whereIn('slug', $slugs)->get()->sortBy(fn (Addon $addon) => array_search($addon->slug, $slugs, true))->values()->all();

        return [
            'boat' => $boat,
            'experience' => $experience,
            'startsAt' => QuoteRequest::localStart($this->validated('date'), $this->validated('time')),
            'hours' => (int) $this->validated('hours'),
            'guests' => (int) $this->validated('guests'),
            'addons' => $addons,
        ];
    }

    /** The choices as a query string for the boat page or checkout (only what passed validation). */
    public function tripQuery(): array
    {
        $data = $this->safe()->only(['experience', 'date', 'time', 'hours', 'guests']);

        return array_filter($data + ['addons' => implode(',', $this->validated('addons') ?? [])], fn ($value) => $value !== null && $value !== '');
    }

    /** Wrong choices go back to the boat's booking card (or the fleet when the boat itself is unknown). */
    protected function getRedirectUrl(): string
    {
        $yacht = $this->input('yacht');

        return is_string($yacht) && preg_match('/^[a-z0-9-]{1,80}$/', $yacht)
            ? route('yachts.show', $yacht).'#book'
            : route('yachts.index');
    }
}
