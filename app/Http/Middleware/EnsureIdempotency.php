<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * `idempotent` (PLAN §8.2 rule 12): a double-click or a retry never does the same thing twice.
 * For holds, payments, refunds, payouts and withdrawals (Phases 4–7); the domain tables add a unique key too.
 *
 * The client sends one key per action: the `Idempotency-Key` header (resources/js/lib/http.js) or, for a
 * plain form, a hidden `idempotency_key` field. Keys are scoped to the account (or the guest's session) and
 * the endpoint, and kept for 24 hours in the cache (`Cache::add` is atomic, so two parallel requests can't
 * both start):
 * - first request → runs; a successful (2xx/3xx) response is stored;
 * - same key, same request again → the stored response is replayed (header `Idempotent-Replayed: true`);
 * - same key, different request → 422; while the first is still running → 409;
 * - a failed request (4xx/5xx) frees the key, so the user can fix the input and try again with it.
 */
class EnsureIdempotency
{
    public const HEADER = 'Idempotency-Key';

    public const INPUT = 'idempotency_key';

    private const HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header(self::HEADER) ?? $request->input(self::INPUT);
        if (! is_string($key) || preg_match('/^[A-Za-z0-9-]{16,100}$/', $key) !== 1) {
            return $this->refuse($request, 400, 'This request has no valid idempotency key. Please refresh the page and try again.');
        }

        $cacheKey = self::cacheKey($request, $key);
        $fingerprint = hash('sha256', (string) json_encode($request->except(['_token', '_method', self::INPUT])));

        if (! Cache::add($cacheKey, ['state' => 'running', 'fingerprint' => $fingerprint], now()->addHours(self::HOURS))) {
            $stored = Cache::get($cacheKey);

            return match (true) {
                ! is_array($stored) || $stored['state'] === 'running' => $this->refuse($request, 409, 'This is already being processed. Please wait a moment.'),
                $stored['fingerprint'] !== $fingerprint => $this->refuse($request, 422, 'This request was already sent with different details. Please refresh the page and try again.'),
                default => response($stored['content'], $stored['status'], [...$stored['headers'], 'Idempotent-Replayed' => 'true']),
            };
        }

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            Cache::forget($cacheKey);
            throw $e;
        }

        if (! $response->isSuccessful() && ! $response->isRedirection()) {
            Cache::forget($cacheKey);

            return $response;
        }

        Cache::put($cacheKey, [
            'state' => 'done',
            'fingerprint' => $fingerprint,
            'status' => $response->getStatusCode(),
            'content' => (string) $response->getContent(),
            'headers' => array_filter(['Content-Type' => $response->headers->get('Content-Type'), 'Location' => $response->headers->get('Location')]),
        ], now()->addHours(self::HOURS));

        return $response;
    }

    /** One entry per account (or guest session) + method + path + key. */
    public static function cacheKey(Request $request, string $key): string
    {
        $owner = $request->user() ? 'user:'.$request->user()->getAuthIdentifier() : 'session:'.$request->session()->getId();

        return 'idempotency:'.hash('sha256', $owner.'|'.$request->method().'|'.$request->path().'|'.$key);
    }

    private function refuse(Request $request, int $status, string $message): Response
    {
        return $request->expectsJson()
            ? response()->json(['message' => $message], $status)
            : $this->back($message);
    }

    private function back(string $message): RedirectResponse
    {
        return back()->withErrors([self::INPUT => $message]);
    }
}
