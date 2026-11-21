<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoCatalog;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Phase 1: demo catalog. Phase 3: Eloquent (Boat, Experience, Testimonial, Faq). */
    public function __invoke(DemoCatalog $catalog): View
    {
        $yachts = $catalog->yachts();

        return view('pages.home', [
            // Fleet tabs: each tab shows up to three boats.
            'fleetTabs' => [
                'all' => ['label' => 'All', 'yachts' => $catalog->featuredYachts()->take(3)],
                'small' => ['label' => 'Up to 13', 'yachts' => $yachts->where('max_guests', '<=', 13)->take(3)->values()],
                'large' => ['label' => '14 – 80 guests', 'yachts' => $yachts->where('max_guests', '>', 13)->take(3)->values()],
            ],
            'experiences' => $catalog->experiences(),
            'stats' => $catalog->content('stats'),
            'rating' => $catalog->content('rating'),
            'trustedBy' => $catalog->content('trusted_by'),
            'testimonials' => $catalog->content('testimonials'),
            'gallery' => $catalog->content('gallery'),
            'faqs' => $catalog->content('faqs'),
            'today' => now(config('app.display_timezone'))->toDateString(),
        ]);
    }
}
