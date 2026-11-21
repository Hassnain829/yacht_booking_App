<?php

/*
|--------------------------------------------------------------------------
| Site settings
|--------------------------------------------------------------------------
|
| Business details, navigation and the promo strip used by the header and
| footer. In Phase 10 these values move to the `settings` table so the
| admin can edit them from the dashboard. Views read them through
| config('site.…') until then.
|
*/

return [

    'business' => [
        'name' => 'Chicago Yacht Booking',
        'domain' => 'chicagoyachtbooking.com',
        'phone' => '(312) 555-0148',
        'phone_href' => '+13125550148',
        'email' => 'hello@chicagoyachtbooking.com',
        'office' => 'DuSable Harbor, Chicago, IL 60601',
        'hours' => 'Daily 8:00 AM – 9:00 PM',
        'tagline' => 'Private yacht charters on Lake Michigan and the Chicago River. Licensed captains, transparent pricing and instant online booking — seven days a week.',
    ],

    'social' => [
        'instagram' => '#',
        'facebook' => '#',
        'youtube' => '#',
    ],

    // Main navigation. `active` = route-name patterns that highlight the link.
    'nav' => [
        ['label' => 'Home', 'route' => 'home', 'active' => ['home']],
        ['label' => 'Yachts', 'route' => 'yachts.index', 'active' => ['yachts.*']],
        ['label' => 'Experiences', 'route' => 'experiences.index', 'active' => ['experiences.*']],
        ['label' => 'Pricing', 'route' => 'pricing', 'active' => ['pricing']],
        ['label' => 'About Us', 'route' => 'about', 'active' => ['about']],
        ['label' => 'Contact Us', 'route' => 'contact', 'active' => ['contact']],
        ['label' => 'List Your Boat', 'route' => 'list-your-boat', 'active' => ['list-your-boat', 'owner.*']],
    ],

    // Promo strip above the header. Changing `id` shows it again to visitors who closed the previous one.
    'promo' => [
        'enabled' => true,
        'id' => 'early-bird-2026',
        'text' => 'Early-bird season pricing — save 10% on your charter with code',
        'code' => 'WELCOME10',
        'cta' => 'Book & save',
    ],

];
