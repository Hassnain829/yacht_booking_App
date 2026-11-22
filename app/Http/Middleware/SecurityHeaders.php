<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (PLAN §8.3), including a strict Content Security Policy:
 * only our own scripts run — no inline scripts, no inline event handlers, no third-party code.
 *
 * Each request gets a fresh nonce; Vite adds it to the tags it renders (@vite, @fonts). Our own
 * markup never needs it (no inline <script>/<style> — checked by the tests).
 * Phase 5 adds Stripe (js.stripe.com, api.stripe.com, hooks.stripe.com) to the lists below.
 */
class SecurityHeaders
{
    /** Google Maps embeds on the contact, yacht and confirmation pages. */
    private const FRAME_SOURCES = ['https://www.google.com', 'https://maps.google.com'];

    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policy($nonce));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), usb=(), payment=(self)');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        if (app()->isProduction() && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        $response->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $scripts = ["'self'", "'nonce-{$nonce}'"];
        $styles = ["'self'", "'nonce-{$nonce}'"];
        $images = ["'self'", 'data:', 'blob:'];
        $fonts = ["'self'", 'data:'];
        $connect = ["'self'"];

        // Local development with `npm run dev`: allow the Vite dev server — its scripts, the CSS and fonts it
        // serves, and the hot-reload websocket. Vite injects <style> tags without a nonce, so styles need
        // 'unsafe-inline' there — never in a build.
        if (Vite::isRunningHot()) {
            [$server, $socket] = $this->devServerSources();
            $scripts[] = $server;
            $styles = ["'self'", "'unsafe-inline'", $server];
            $images[] = $server;
            $fonts[] = $server;
            array_push($connect, $server, $socket);
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $scripts,
            'style-src' => $styles,
            'img-src' => $images,
            'font-src' => $fonts,
            'connect-src' => $connect,
            'media-src' => ["'self'"],
            'frame-src' => self::FRAME_SOURCES,
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        $policy = collect($directives)->map(fn (array $values, string $name) => $name.' '.implode(' ', $values))->implode('; ');

        return app()->isProduction() ? $policy.'; upgrade-insecure-requests' : $policy;
    }

    /**
     * The Vite dev server (from the hot file) as CSP sources: [page assets, websocket].
     * CSP has no way to write an IPv6 address such as http://[::1]:5173 (browsers drop it, and the CSS
     * is blocked), so then only the scheme is allowed — local development only. vite.config.js pins the
     * dev server to 127.0.0.1, so normally the exact origin is used.
     *
     * @return array{0: string, 1: string}
     */
    private function devServerSources(): array
    {
        $origin = rtrim(trim((string) file_get_contents(Vite::hotFile())), '/');
        $secure = str_starts_with($origin, 'https:');

        if ($origin === '' || str_contains($origin, '[')) {
            return $secure ? ['https:', 'wss:'] : ['http:', 'ws:'];
        }

        return [$origin, ($secure ? 'wss' : 'ws').substr($origin, $secure ? 5 : 4)];
    }
}
