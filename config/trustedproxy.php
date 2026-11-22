<?php

/*
|--------------------------------------------------------------------------
| Trusted proxies (PLAN §8.3)
|--------------------------------------------------------------------------
|
| Read by Laravel's TrustProxies middleware. By default no proxy is trusted:
| on plain Namecheap hosting visitors talk to the server directly, so the
| X-Forwarded-* headers are ignored. Otherwise anyone could fake their IP
| (and dodge the IP rate limits) or claim to be on HTTPS.
|
| Behind a proxy or CDN that terminates SSL (e.g. Cloudflare), set
| TRUSTED_PROXIES to its IP addresses / ranges, comma separated.
|
*/

return [

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
