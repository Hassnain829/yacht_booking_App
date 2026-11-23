<?php

namespace App\Http\Controllers;

use App\Services\PublicCatalog;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;
use Illuminate\Support\Fluent;

/**
 * For search engines (PLAN 3.6, 10.2): /sitemap.xml lists the public pages and every live boat (from the
 * cached PublicCatalog, so it follows the admin's changes), and /robots.txt keeps crawlers out of private
 * areas — and out of everything on a non-production copy (staging, local), so test sites never get indexed.
 */
class SeoController extends Controller
{
    /** Private areas: never useful in search results (they are also `noindex` or behind a login). */
    public const PRIVATE_PATHS = ['/admin', '/owner', '/account', '/checkout', '/booking/', '/files/', '/list-your-boat', '/user/', '/two-factor-challenge', '/email/', '/reset-password', '/dev/'];

    public function sitemap(PublicCatalog $catalog): Response
    {
        $pages = collect([
            ['url' => route('home'), 'priority' => '1.0', 'changefreq' => 'weekly'],
            ['url' => route('yachts.index'), 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['url' => route('experiences.index'), 'priority' => '0.9', 'changefreq' => 'weekly'],
            ['url' => route('pricing'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['url' => route('booking.create'), 'priority' => '0.7', 'changefreq' => 'monthly'],
            ['url' => route('about'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['url' => route('contact'), 'priority' => '0.6', 'changefreq' => 'monthly'],
            ['url' => route('legal.cancellation'), 'priority' => '0.3', 'changefreq' => 'yearly'],
            ['url' => route('legal.terms'), 'priority' => '0.2', 'changefreq' => 'yearly'],
            ['url' => route('legal.privacy'), 'priority' => '0.2', 'changefreq' => 'yearly'],
        ])->merge($catalog->yachts()->map(fn (Fluent $yacht) => [
            'url' => route('yachts.show', $yacht->slug),
            'priority' => '0.8',
            'changefreq' => 'weekly',
            'lastmod' => $yacht->updated_at ? CarbonImmutable::parse($yacht->updated_at)->utc()->toDateString() : null,
        ]));

        return response()
            ->view('seo.sitemap', ['pages' => $pages], 200)
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        $lines = ['User-agent: *'];
        if (app()->environment('production')) {
            foreach (self::PRIVATE_PATHS as $path) {
                $lines[] = 'Disallow: '.$path;
            }
            $lines[] = '';
            $lines[] = 'Sitemap: '.route('sitemap');
        } else {
            $lines[] = 'Disallow: /'; // staging / local copies are never indexed
        }

        return response(implode("\n", $lines)."\n", 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
