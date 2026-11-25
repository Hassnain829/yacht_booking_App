<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Site settings the admin changes in Admin → Settings (PLAN §6.7, 3.4.5). Read with `get('tax_rate_bp')`: one
 * cached array of every key (cleared on save), falling back to DEFAULTS for a key not saved yet. Saving writes
 * only the keys that changed and one audit entry "settings.updated" with before / after and the reason.
 *
 * Money in cents, rates in basis points, durations in the unit the key names.
 */
class SettingsService
{
    public const CACHE_KEY = 'settings.all';

    /** @var array<string, array<string, mixed>> group => key => default */
    public const DEFAULTS = [
        'booking' => [
            'tax_rate_bp' => 900,
            'weekend_multiplier_bp' => 11500,
            'gratuity_options' => [0, 15, 18, 20],
            'default_gratuity' => 18,
            'hold_minutes' => 15,
        ],
        'owners' => [
            'commission_bp' => 2000,
            'payout_delay_hours' => 72,
            'complaint_window_hours' => 72,
            'min_withdrawal_cents' => 5000,
        ],
        'captains' => [
            'captain_offer_hours' => 24,
            'captain_release_hours' => 48,
            'captain_ack_reminder_hours' => 12,
            'captain_turnaround_minutes' => 30,
        ],
        'extensions' => [
            'extension_cutoff_minutes' => 30,
            'extension_hold_minutes' => 10,
            'extension_max_trip_hours' => 8,
        ],
        'business' => [
            'business_name' => 'Chicago Yacht Booking',
            'business_email' => 'hello@chicagoyachtbooking.com',
            'business_phone' => '(312) 555-0148',
            'business_office' => 'DuSable Harbor, Chicago, IL 60601',
            'business_hours' => 'Daily 8:00 AM – 9:00 PM',
            'business_tagline' => 'Private yacht charters on Lake Michigan and the Chicago River.',
            'social_links' => [],
        ],
    ];

    public function __construct(private AuditService $audit) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->all()[$key] ?? $default;
    }

    /** @return array<string, mixed> every key: the saved value, or its default */
    public function all(): array
    {
        $saved = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        return array_merge(self::defaults(), $saved);
    }

    /**
     * Saves the given keys (only known keys; unchanged ones are skipped) and writes the audit log.
     *
     * @param  array<string, mixed>  $values
     * @return list<string> the keys that changed
     */
    public function update(array $values, User $actor, string $reason): array
    {
        $groups = self::groups();
        $unknown = array_diff(array_keys($values), array_keys($groups));
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown setting: '.implode(', ', $unknown));
        }

        $current = $this->all();
        $changed = array_keys(array_filter($values, fn ($value, $key) => $current[$key] !== $value, ARRAY_FILTER_USE_BOTH));
        if ($changed === []) {
            return [];
        }

        DB::transaction(function () use ($values, $changed, $current, $groups, $actor, $reason): void {
            foreach ($changed as $key) {
                Setting::query()->updateOrCreate(['key' => $key], ['value' => $values[$key], 'group' => $groups[$key]]);
            }
            $this->audit->record(
                'settings.updated',
                null,
                array_intersect_key($current, array_flip($changed)),
                array_intersect_key($values, array_flip($changed)),
                $reason,
                $actor,
            );
        });
        Cache::forget(self::CACHE_KEY);

        return $changed;
    }

    /** @return array<string, mixed> key => default */
    public static function defaults(): array
    {
        return array_merge(...array_values(self::DEFAULTS));
    }

    /** @return array<string, string> key => group */
    public static function groups(): array
    {
        $groups = [];
        foreach (self::DEFAULTS as $group => $keys) {
            foreach (array_keys($keys) as $key) {
                $groups[$key] = $group;
            }
        }

        return $groups;
    }
}
