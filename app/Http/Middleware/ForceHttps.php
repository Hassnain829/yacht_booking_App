<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * HTTPS everywhere (PLAN §8.3): in production a plain-HTTP request is sent to the same URL over HTTPS —
 * 301 for GET/HEAD, 308 for other methods (the browser repeats the same method and body).
 *
 * Runs after Laravel's TrustProxies. Behind a proxy or CDN that terminates SSL (e.g. Cloudflare), set
 * TRUSTED_PROXIES (config/trustedproxy.php) so its `X-Forwarded-Proto: https` counts; otherwise every
 * request looks insecure and the redirect would loop.
 */
class ForceHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app()->isProduction() && ! $request->isSecure()) {
            // Built from the host + raw request URI, so a path like "//evil.example" stays on our host.
            return redirect()->away('https://'.$request->getHttpHost().$request->getRequestUri(), $request->isMethodSafe() ? 301 : 308);
        }

        return $next($request);
    }
}
