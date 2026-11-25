<?php

namespace App\Http\Requests\Admin;

use App\Rules\PlainText;
use App\Support\Money;

/**
 * Admin → Settings (PLAN 3.4.5). The admin types percents, dollars, hours and minutes; values() turns them into
 * the stored units (basis points, cents). Saving needs a reason and the password (§8.2 rule 15), shown in the
 * form itself, so errors use the default bag.
 */
class SettingsRequest extends SensitiveActionRequest
{
    public const GRATUITY_CHOICES = [0, 10, 15, 18, 20, 25];

    protected $errorBag = 'default';

    public function authorize(): bool
    {
        return $this->user()->can('manage-settings');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'tax_rate' => ['required', 'numeric', 'decimal:0,2', 'between:0,25'],
            'weekend_markup' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'hold_minutes' => ['required', 'integer', 'between:5,60'],
            'gratuity_options' => ['required', 'array', 'min:1', 'max:'.count(self::GRATUITY_CHOICES)],
            'gratuity_options.*' => ['integer', 'distinct', 'in:'.implode(',', self::GRATUITY_CHOICES)],
            'default_gratuity' => ['required', 'integer', 'in_array:gratuity_options.*'],
            'commission' => ['required', 'numeric', 'decimal:0,2', 'between:0,50'],
            'payout_delay_hours' => ['required', 'integer', 'between:0,720'],
            'complaint_window_hours' => ['required', 'integer', 'between:0,720'],
            'min_withdrawal' => ['required', 'numeric', 'decimal:0,2', 'between:0,10000'],
            'captain_offer_hours' => ['required', 'integer', 'between:1,168'],
            'captain_release_hours' => ['required', 'integer', 'between:0,336'],
            'captain_ack_reminder_hours' => ['required', 'integer', 'between:1,72'],
            'captain_turnaround_minutes' => ['required', 'integer', 'between:0,240'],
            'extension_cutoff_minutes' => ['required', 'integer', 'between:0,120'],
            'extension_hold_minutes' => ['required', 'integer', 'between:5,30'],
            'extension_max_trip_hours' => ['required', 'integer', 'between:2,24'],
            'business_name' => ['required', 'string', 'max:80', new PlainText],
            'business_email' => ['required', 'string', 'max:254', 'email:rfc,strict'],
            'business_phone' => ['required', 'string', 'max:30', 'regex:/^[0-9+().\-\s]{7,30}$/'],
            'business_office' => ['nullable', 'string', 'max:160', new PlainText],
            'business_hours' => ['nullable', 'string', 'max:80', new PlainText],
            'business_tagline' => ['nullable', 'string', 'max:255', new PlainText],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'default_gratuity.in_array' => 'The default tip must be one of the options you ticked.',
            'business_phone.regex' => 'Use digits, spaces and + ( ) - only.',
        ];
    }

    /**
     * The settings in their stored units, keyed like SettingsService::DEFAULTS.
     *
     * @return array<string, mixed>
     */
    public function values(): array
    {
        $v = fn (string $key) => $this->validated($key);
        $options = array_map('intval', $v('gratuity_options'));
        sort($options);

        return [
            'tax_rate_bp' => Money::toBasisPoints($v('tax_rate')),
            'weekend_multiplier_bp' => 10000 + (int) Money::toBasisPoints($v('weekend_markup')),
            'hold_minutes' => (int) $v('hold_minutes'),
            'gratuity_options' => $options,
            'default_gratuity' => (int) $v('default_gratuity'),
            'commission_bp' => Money::toBasisPoints($v('commission')),
            'payout_delay_hours' => (int) $v('payout_delay_hours'),
            'complaint_window_hours' => (int) $v('complaint_window_hours'),
            'min_withdrawal_cents' => Money::toCents($v('min_withdrawal')),
            'captain_offer_hours' => (int) $v('captain_offer_hours'),
            'captain_release_hours' => (int) $v('captain_release_hours'),
            'captain_ack_reminder_hours' => (int) $v('captain_ack_reminder_hours'),
            'captain_turnaround_minutes' => (int) $v('captain_turnaround_minutes'),
            'extension_cutoff_minutes' => (int) $v('extension_cutoff_minutes'),
            'extension_hold_minutes' => (int) $v('extension_hold_minutes'),
            'extension_max_trip_hours' => (int) $v('extension_max_trip_hours'),
            'business_name' => (string) $v('business_name'),
            'business_email' => strtolower((string) $v('business_email')),
            'business_phone' => (string) $v('business_phone'),
            'business_office' => (string) ($v('business_office') ?? ''),
            'business_hours' => (string) ($v('business_hours') ?? ''),
            'business_tagline' => (string) ($v('business_tagline') ?? ''),
        ];
    }
}
