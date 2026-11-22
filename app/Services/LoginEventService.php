<?php

namespace App\Services;

use App\Enums\LoginEventType;
use App\Models\LoginEvent;
use App\Models\User;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;

/**
 * Writes the `login_events` security log (PLAN §5, §8.5) and recognises devices for the
 * new-device email (§8.1).
 *
 * A "device" is a browser that holds our long-lived `cyb_device` cookie: a random value, encrypted
 * like every cookie. The log stores only its SHA-256, so a leaked row can't be replayed as the cookie.
 */
class LoginEventService
{
    public const DEVICE_COOKIE = 'cyb_device';

    /** Browsers cap cookie lifetimes at 400 days. */
    private const DEVICE_COOKIE_MINUTES = 60 * 24 * 400;

    public function record(LoginEventType $type, ?User $user, ?string $email = null, ?string $deviceId = null): LoginEvent
    {
        // Artisan commands (e.g. app:reset-two-factor) have no visitor: no IP or browser to record.
        $console = app()->runningInConsole() && ! app()->runningUnitTests();
        $request = request();
        $email = Str::lower(trim((string) ($email ?? $user?->email)));

        return LoginEvent::create([
            'user_id' => $user?->getKey(),
            'email' => $email === '' ? null : mb_substr($email, 0, 254),
            'event' => $type,
            'ip_address' => $console ? null : $request->ip(),
            'user_agent' => $console || ! $request->userAgent() ? null : mb_substr($request->userAgent(), 0, 500),
            'device_id' => $deviceId,
        ]);
    }

    /** This browser's device id (hashed). A browser without a valid device cookie gets a new one. */
    public function currentDevice(): string
    {
        $value = request()->cookie(self::DEVICE_COOKIE);

        if (! is_string($value) || preg_match('/^[A-Za-z0-9]{40}$/', $value) !== 1) {
            $value = Str::random(40);
            Cookie::queue(self::DEVICE_COOKIE, $value, self::DEVICE_COOKIE_MINUTES); // path, secure, SameSite: as the session cookie
        }

        return hash('sha256', $value);
    }

    public function hasLoggedInBefore(User $user): bool
    {
        return LoginEvent::query()->where('user_id', $user->getKey())->where('event', LoginEventType::Login)->exists();
    }

    public function knowsDevice(User $user, string $deviceId): bool
    {
        return LoginEvent::query()->where('user_id', $user->getKey())->where('event', LoginEventType::Login)->where('device_id', $deviceId)->exists();
    }
}
