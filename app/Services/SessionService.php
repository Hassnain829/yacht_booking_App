<?php

namespace App\Services;

use App\Enums\LoginEventType;
use App\Models\User;
use App\Support\UserAgent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;

/**
 * Where an account is logged in, and ending those sessions (PLAN §8.1).
 *
 * Reads the database session store (SESSION_DRIVER=database). Only its plain columns are used
 * (IP, browser, last activity); the encrypted session payload is never opened.
 */
class SessionService
{
    /** The Devices list shows at most this many other sessions. */
    private const LIST_LIMIT = 20;

    public function __construct(private LoginEventService $log) {}

    /**
     * This device first, then the other sessions by last activity.
     *
     * @return Collection<int, Fluent>
     */
    public function devicesFor(User $user, Request $request): Collection
    {
        $currentId = $request->session()->getId();
        $others = $this->usesDatabase()
            ? $this->table()->where('user_id', $user->getKey())->where('id', '!=', $currentId)
                ->orderByDesc('last_activity')->limit(self::LIST_LIMIT)->get(['ip_address', 'user_agent', 'last_activity'])
            : collect();

        return $others
            ->map(fn (object $row) => $this->device($row->ip_address, $row->user_agent, CarbonImmutable::createFromTimestamp((int) $row->last_activity), current: false))
            ->prepend($this->device($request->ip(), $request->userAgent(), CarbonImmutable::now(), current: true))
            ->values();
    }

    /**
     * "Log out other devices": ends every other session of the user. Returns how many ended.
     * "Remember me" stops working on every browser too; this browser stays logged in on its session.
     */
    public function logOutOtherDevices(User $user, Request $request): int
    {
        $ended = $this->endSessions($user, except: $request->session()->getId());
        $this->log->record(LoginEventType::OtherDevicesLoggedOut, $user);

        return $ended;
    }

    /** Ends every session of the user, everywhere (suspension, ban, password reset, 2FA reset). */
    public function logOutEverywhere(User $user): int
    {
        return $this->endSessions($user);
    }

    private function endSessions(User $user, ?string $except = null): int
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();

        if (! $this->usesDatabase()) {
            return 0;
        }

        return $this->table()->where('user_id', $user->getKey())
            ->when($except !== null, fn (Builder $query) => $query->where('id', '!=', $except))
            ->delete();
    }

    private function device(?string $ip, ?string $userAgent, CarbonImmutable $lastActive, bool $current): Fluent
    {
        return new Fluent([
            'device' => UserAgent::describe($userAgent),
            'icon' => UserAgent::icon($userAgent),
            'ip' => $ip ?? 'Unknown IP',
            'last_active' => $lastActive,
            'current' => $current,
        ]);
    }

    private function usesDatabase(): bool
    {
        return config('session.driver') === 'database';
    }

    private function table(): Builder
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'));
    }
}
