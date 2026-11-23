<?php

namespace App\Http\Requests\Admin;

use App\Enums\AmenityGroup;
use App\Enums\BoatType;
use App\Enums\CaptainSource;
use App\Http\Requests\Admin\Concerns\PreparesSlug;
use App\Models\Amenity;
use App\Models\Boat;
use App\Models\CancellationPolicy;
use App\Models\Experience;
use App\Models\Harbor;
use App\Rules\PlainText;
use App\Support\Admin\BoatOptions;
use App\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Add or edit a boat as the admin (PLAN 3.4.3–3.4.4): details, specs, location, pricing (dollars → cents),
 * booking rules, operation, display, the site switches, plus amenities, the experiences it offers (with an
 * optional package-fee override) and weekly opening hours. Never accepted from the form: ownership, owner,
 * status (published only through the status buttons) — PLAN §8.2.
 *
 * Wizard rules kept (PLAN §7.7): boats over 32 ft can't be operated by the renter.
 */
class BoatRequest extends FormRequest
{
    use PreparesSlug;

    public function authorize(): bool
    {
        $boat = $this->route('boat');

        return $boat instanceof Boat
            ? $this->user()->can('update', $boat)
            : $this->user()->can('create', Boat::class);
    }

    protected function prepareForValidation(): void
    {
        $this->prepareSlug('title');

        // "Add a missing item" boxes: comma-separated names per amenity group → lists.
        $new = [];
        foreach ((array) $this->input('new_amenities', []) as $group => $text) {
            // An empty box arrives as null (ConvertEmptyStringsToNull) — that's simply "nothing to add".
            $new[$group] = match (true) {
                is_string($text) => array_values(array_filter(array_map('trim', explode(',', $text)), fn (string $name) => $name !== '')),
                $text === null => [],
                default => $text,
            };
        }
        $this->merge(['highlights' => $this->lines('highlights_text'), 'new_amenity_names' => $new]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $boat = $this->route('boat');
        $year = (int) now()->year + 1;

        return [
            // Details
            'title' => ['required', 'string', 'max:120', new PlainText],
            'slug' => ['required', 'string', 'max:120', self::SLUG_RULE, Rule::unique('boats', 'slug')->ignore($boat instanceof Boat ? $boat->getKey() : null)],
            'short' => ['nullable', 'string', 'max:255', new PlainText],
            'description' => ['nullable', 'string', 'max:5000', new PlainText],
            'badge' => ['nullable', 'string', 'max:60', new PlainText],
            'highlights_text' => ['nullable', 'string', 'max:1500'],
            'highlights' => ['array', 'max:12'],
            'highlights.*' => ['string', 'max:80', 'distinct', new PlainText],
            // Specs
            'type' => ['required', Rule::enum(BoatType::class)],
            // The wizard's list, plus the boat's current category (demo boats use the mockup's own, e.g. "Superyacht").
            'category' => ['required', 'string', 'max:60', Rule::in(array_keys(BoatOptions::categories($boat instanceof Boat ? $boat->category : null)))],
            'make' => ['nullable', 'string', 'max:80', new PlainText],
            'model' => ['nullable', 'string', 'max:80', new PlainText],
            'year' => ['nullable', 'integer', 'between:1950,'.$year],
            'length_ft' => ['required', 'integer', 'between:10,400'],
            'max_guests' => ['required', 'integer', 'between:1,150'],
            'staterooms' => ['nullable', 'integer', 'between:0,50'],
            'sleeps' => ['nullable', 'integer', 'between:0,100'],
            'beds' => ['nullable', 'integer', 'between:0,100'],
            'heads' => ['nullable', 'integer', 'between:0,50'],
            'engine_count' => ['nullable', 'integer', 'between:0,10'],
            'engine_type' => ['nullable', 'string', Rule::in(array_keys(BoatOptions::engineTypes()))],
            'engine_model' => ['nullable', 'string', 'max:80', new PlainText],
            'horsepower' => ['nullable', 'integer', 'between:0,20000'],
            'engine_year' => ['nullable', 'integer', 'between:1950,'.$year],
            'uscg_inspected' => ['required', 'boolean'],
            'registration_no' => ['nullable', 'string', 'max:60', new PlainText],
            // Location
            'harbor' => ['nullable', 'string', Rule::exists('harbors', 'slug')->whereNull('deleted_at')],
            'street' => ['nullable', 'string', 'max:255', new PlainText],
            'city' => ['nullable', 'string', 'max:120', new PlainText],
            'state' => ['nullable', 'string', Rule::in(array_keys(BoatOptions::states()))],
            'zip' => ['nullable', 'string', 'regex:/^\d{5}(-\d{4})?$/'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90', 'decimal:0,6'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180', 'decimal:0,6'],
            // Pricing
            'hourly_rate' => ['required', 'numeric', 'decimal:0,2', 'min:'.config('listing.pricing.hourly_min_cents') / 100, 'max:'.config('listing.pricing.hourly_max_cents') / 100],
            'weekend_markup' => ['nullable', Rule::in(array_keys(BoatOptions::weekendMarkups()))],
            'min_hours' => ['required', 'integer', 'between:1,12'],
            'durations' => ['nullable', 'array', 'max:'.count(BoatOptions::DURATIONS)],
            'durations.*' => ['integer', 'distinct', Rule::in(BoatOptions::DURATIONS)],
            'duration_prices' => ['nullable', 'array', 'max:'.count(BoatOptions::DURATIONS)],
            'duration_prices.*' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:'.config('listing.pricing.fixed_price_max_cents') / 100],
            'cleaning_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:'.config('listing.pricing.cleaning_fee_max_cents') / 100],
            'captain_rate' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:10000'],
            'crew_count' => ['required', 'integer', 'between:1,20'],
            // Booking rules
            'cancellation_policy' => ['required', 'string', Rule::exists('cancellation_policies', 'slug')->where('is_active', true)],
            'advance_notice_minutes' => ['required', 'integer', Rule::in(array_keys(BoatOptions::ADVANCE_NOTICE))],
            'buffer_minutes' => ['required', 'integer', Rule::in(array_keys(BoatOptions::BUFFERS))],
            'allow_multiple_bookings' => ['required', 'boolean'],
            'not_allowed' => ['nullable', 'array', 'max:'.count(BoatOptions::rules())],
            'not_allowed.*' => ['string', 'distinct', Rule::in(BoatOptions::rules())],
            // Operation
            'captain_source' => ['required', Rule::enum(CaptainSource::class)],
            'renter_operated_allowed' => ['required', 'boolean'],
            'fishing_charter' => ['required', 'boolean'],
            'fuel_policy' => ['nullable', 'string', 'max:60', new PlainText],
            // Display
            'is_featured' => ['required', 'boolean'],
            'sort' => ['required', 'integer', 'between:0,9999'],
            'rating_avg' => ['nullable', 'numeric', 'decimal:0,2', 'between:0,5'],
            'reviews_count' => ['required', 'integer', 'between:0,100000'],
            // On the site
            'booking_enabled' => ['required', 'boolean'],
            'unavailable_reason' => ['nullable', 'string', 'max:255', new PlainText],
            'extensions_enabled' => ['required', 'boolean'],
            'publish' => ['nullable', 'boolean'],
            // Amenities, experiences, opening hours
            'amenities' => ['nullable', 'array', 'max:200'],
            'amenities.*' => ['string', 'distinct', Rule::exists('amenities', 'ulid')],
            'new_amenities' => ['nullable', 'array:'.implode(',', array_map(fn (AmenityGroup $g) => $g->value, AmenityGroup::forms()))],
            'new_amenities.*' => ['nullable', 'string', 'max:300'],
            'new_amenity_names' => ['array'],
            'new_amenity_names.*' => ['array', 'max:10'],
            'new_amenity_names.*.*' => ['string', 'max:80', new PlainText],
            'experiences' => ['nullable', 'array', 'max:100'],
            'experiences.*' => ['array:offered,fee'],
            'experiences.*.offered' => ['nullable', 'boolean'],
            'experiences.*.fee' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:100000'],
            'hours' => ['required', 'array:0,1,2,3,4,5,6'],
            'hours.*' => ['array:open,opens,closes'],
            'hours.*.open' => ['nullable', 'boolean'],
            'hours.*.opens' => ['nullable', 'date_format:H:i'],
            'hours.*.closes' => ['nullable', 'date_format:H:i'],
        ];
    }

    /**
     * Checks that need several fields: experience keys, opening hours, the 32 ft renter rule.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $known = Experience::query()->pluck('slug')->all();
            foreach (array_keys((array) $this->input('experiences', [])) as $slug) {
                if (! in_array($slug, $known, true)) {
                    $validator->errors()->add('experiences', 'One of the experiences no longer exists. Reload the page and try again.');
                    break;
                }
            }

            foreach ((array) $this->input('hours', []) as $day => $hours) {
                if (! filter_var($hours['open'] ?? false, FILTER_VALIDATE_BOOL)) {
                    continue;
                }
                if (blank($hours['opens'] ?? null) || blank($hours['closes'] ?? null)) {
                    $validator->errors()->add("hours.{$day}.opens", 'Enter the opening and closing time, or untick the day.');
                } elseif ($hours['closes'] <= $hours['opens']) {
                    $validator->errors()->add("hours.{$day}.closes", 'Closing time must be after opening time.');
                }
            }

            if ($this->boolean('renter_operated_allowed') && (int) $this->input('length_ft') > (int) config('listing.rules_engine.renter_max_length_ft')) {
                $validator->errors()->add('renter_operated_allowed', 'Boats longer than '.config('listing.rules_engine.renter_max_length_ft').' ft must be captained.');
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->slugMessages('boat') + [
            'harbor.exists' => 'Choose one of the harbors in the list.',
            'zip.regex' => 'Enter a 5-digit ZIP code (or ZIP+4).',
            'hourly_rate.min' => 'The hourly rate must be at least $:min.',
            'highlights.max' => 'Keep it to 12 highlights or fewer.',
            'highlights.*.max' => 'Each highlight can be at most 80 characters.',
            'new_amenity_names.*.max' => 'Add at most 10 new items to one list at a time.',
            'new_amenity_names.*.*.max' => 'Each new item can be at most 80 characters.',
        ];
    }

    /**
     * The fillable fields saved on the boat.
     *
     * @return array<string, mixed>
     */
    public function boatData(): array
    {
        $data = $this->safe()->only([
            'title', 'slug', 'short', 'description', 'badge',
            'type', 'category', 'make', 'model', 'year', 'length_ft', 'max_guests', 'staterooms', 'sleeps', 'beds', 'heads',
            'engine_count', 'engine_type', 'engine_model', 'horsepower', 'engine_year', 'uscg_inspected', 'registration_no',
            'street', 'city', 'state', 'zip', 'lat', 'lng',
            'min_hours', 'crew_count', 'advance_notice_minutes', 'buffer_minutes', 'allow_multiple_bookings',
            'captain_source', 'renter_operated_allowed', 'fishing_charter', 'fuel_policy',
            'is_featured', 'sort', 'rating_avg', 'reviews_count', 'booking_enabled', 'unavailable_reason', 'extensions_enabled',
        ]);

        $durations = array_map('intval', $this->validated('durations') ?? []);
        sort($durations);
        $prices = collect($this->validated('duration_prices') ?? [])
            ->only(array_map('strval', $durations))
            ->map(fn ($dollars) => Money::toCents($dollars))
            ->filter(fn ($cents) => $cents !== null)
            ->all();
        $markup = $this->validated('weekend_markup');

        return array_merge($data, [
            'harbor_id' => filled($this->validated('harbor')) ? Harbor::query()->where('slug', $this->validated('harbor'))->value('id') : null,
            'cancellation_policy_id' => CancellationPolicy::query()->where('slug', $this->validated('cancellation_policy'))->value('id'),
            'hourly_rate_cents' => Money::toCents($this->validated('hourly_rate')),
            'weekend_multiplier_bp' => blank($markup) ? null : 10000 + (int) $markup,
            'durations' => $durations === [] ? null : $durations,
            'duration_prices' => $prices === [] ? null : $prices,
            'cleaning_fee_cents' => Money::toCents($this->validated('cleaning_fee')),
            'captain_rate_cents' => Money::toCents($this->validated('captain_rate')),
            'rules' => ['not_allowed' => array_values($this->validated('not_allowed') ?? [])],
            'highlights' => $this->validated('highlights') === [] ? null : $this->validated('highlights'),
            'unavailable_reason' => $this->boolean('booking_enabled') ? null : $this->validated('unavailable_reason'),
        ]);
    }

    /**
     * Amenity ids (the form sends ulids) + new item names per group, offered experiences (id => package fee override in cents or null) and the 7 opening-hour rows.
     *
     * @return array{amenities: list<int>, new_amenities: array<string, list<string>>, experiences: array<int, ?int>, hours: list<array{weekday: int, is_open: bool, opens_at: ?string, closes_at: ?string}>}
     */
    public function relationData(): array
    {
        $chosen = collect($this->validated('experiences') ?? [])->filter(fn (array $row) => filter_var($row['offered'] ?? false, FILTER_VALIDATE_BOOL));
        $ids = Experience::query()->whereIn('slug', $chosen->keys())->pluck('id', 'slug');

        return [
            'amenities' => Amenity::query()->whereIn('ulid', $this->validated('amenities') ?? [])->pluck('id')->map(fn ($id) => (int) $id)->all(),
            'new_amenities' => collect($this->validated('new_amenity_names') ?? [])->filter()->all(),
            'experiences' => $chosen->mapWithKeys(fn (array $row, string $slug) => [$ids[$slug] => Money::toCents($row['fee'] ?? null)])->all(),
            'hours' => collect(range(0, 6))->map(function (int $day) {
                $row = $this->validated('hours')[$day] ?? [];
                $open = filter_var($row['open'] ?? false, FILTER_VALIDATE_BOOL);

                // Stored as HH:MM:SS, the same on MySQL / MariaDB (TIME) and SQLite (tests).
                return ['weekday' => $day, 'is_open' => $open, 'opens_at' => $open ? $row['opens'].':00' : null, 'closes_at' => $open ? $row['closes'].':00' : null];
            })->all(),
        ];
    }
}
