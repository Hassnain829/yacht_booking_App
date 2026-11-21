<?php

/*
|--------------------------------------------------------------------------
| Demo catalog (Phase 1 only)
|--------------------------------------------------------------------------
|
| A copy of the mockup's assets/js/data.js, reshaped to match the database
| columns planned in docs/PLAN.md §6 (money in cents, rates in basis points,
| slugs as identifiers). Read it through App\Support\Demo\DemoCatalog.
|
| Phase 3 replaces DemoCatalog with Eloquent models and seeds these same
| values into the database. Then this file is deleted.
|
| All prices are PLACEHOLDERS.
|
*/

return [

    'harbors' => [
        ['slug' => 'dusable', 'name' => 'DuSable Harbor', 'area' => 'Downtown · Streeterville', 'notes' => 'Steps from Millennium Park and Maggie Daley Park.', 'map_query' => 'DuSable Harbor Chicago IL', 'image' => 'images/chicago-navy-pier.jpg'],
        ['slug' => 'navypier', 'name' => 'Navy Pier', 'area' => 'Streeterville', 'notes' => 'Board right next to the Centennial Wheel.', 'map_query' => 'Navy Pier Chicago IL', 'image' => 'images/chicago-river-cruise.jpg'],
        ['slug' => 'belmont', 'name' => 'Belmont Harbor', 'area' => 'Lakeview', 'notes' => 'Calm north-side harbor, easy street parking.', 'map_query' => 'Belmont Harbor Chicago IL', 'image' => 'images/chicago-river-boats.jpg'],
        ['slug' => 'burnham', 'name' => 'Burnham Harbor', 'area' => 'Museum Campus', 'notes' => 'Skyline views from the moment you board.', 'map_query' => 'Burnham Harbor Chicago IL', 'image' => 'images/chicago-architecture.jpg'],
    ],

    'yachts' => [
        [
            'slug' => 'azure', 'title' => 'The Azure', 'category' => 'Motor Yacht', 'length_ft' => 62, 'max_guests' => 13, 'staterooms' => 3, 'heads' => 2, 'year' => 2021,
            'harbor' => 'dusable', 'hourly_rate_cents' => 45000, 'min_hours' => 3, 'captain_rate_cents' => 8500, 'crew_count' => 1, 'cleaning_fee_cents' => 15000,
            'rating_avg' => 4.9, 'reviews_count' => 312, 'badge' => 'Most Popular', 'is_featured' => true, 'booking_enabled' => true,
            'cover' => 'images/yacht-azure.jpg',
            'gallery' => ['images/yacht-azure.jpg', 'images/deck-lounge.jpg', 'images/interior-salon.jpg', 'images/yacht-aerial.jpg', 'images/deck-aft.jpg'],
            'short' => 'A sleek flybridge yacht with a sun-drenched upper deck — our most-booked boat for birthdays and Playpen days.',
            'description' => 'The Azure pairs a wide-open flybridge with a cool, air-conditioned salon, so your group can chase the sun or retreat to the shade. A swim platform at the stern makes Playpen days effortless, and the premium sound system keeps the energy right from the moment you leave the harbor.',
            'amenities' => ['Flybridge sun lounge', 'Premium Bluetooth sound', 'Swim platform & ladder', 'Air-conditioned salon', '2 private restrooms', 'Coolers & ice', 'Lily pad compatible', 'Shaded seating'],
            'experiences' => ['sunset', 'playpen', 'river', 'fireworks', 'celebration', 'proposal', 'private'],
        ],
        [
            'slug' => 'skyline', 'title' => 'Skyline Majesty', 'category' => 'Luxury Yacht', 'length_ft' => 75, 'max_guests' => 49, 'staterooms' => 4, 'heads' => 3, 'year' => 2019,
            'harbor' => 'navypier', 'hourly_rate_cents' => 95000, 'min_hours' => 3, 'captain_rate_cents' => 17500, 'crew_count' => 3, 'cleaning_fee_cents' => 25000,
            'rating_avg' => 5.0, 'reviews_count' => 128, 'badge' => 'Best for Groups', 'is_featured' => true, 'booking_enabled' => true,
            'cover' => 'images/yacht-skyline-majesty.jpg',
            'gallery' => ['images/yacht-skyline-majesty.jpg', 'images/interior-salon.jpg', 'images/exp-party.jpg', 'images/deck-aft.jpg', 'images/chicago-navy-pier.jpg'],
            'short' => 'Three decks, a full bar setup and room for 49 — built for company outings, weddings and milestone parties.',
            'description' => 'Skyline Majesty is a tri-deck yacht designed for hosting. A climate-controlled main salon flows onto an open aft deck, while the top deck delivers 360° views of the Chicago skyline. A captain and two crew members handle everything so you can focus on your guests.',
            'amenities' => ['Three entertaining decks', 'Built-in bar & catering prep', 'Climate-controlled salon', '3 restrooms', 'Pro sound & mic', 'Dance floor', 'Shaded & open seating', 'Captain + 2 crew'],
            'experiences' => ['sunset', 'fireworks', 'celebration', 'corporate', 'private'],
        ],
        [
            'slug' => 'windward', 'title' => 'Windward', 'category' => 'Sailboat', 'length_ft' => 42, 'max_guests' => 6, 'staterooms' => 2, 'heads' => 1, 'year' => 2018,
            'harbor' => 'belmont', 'hourly_rate_cents' => 22500, 'min_hours' => 2, 'captain_rate_cents' => 6500, 'crew_count' => 1, 'cleaning_fee_cents' => 7500,
            'rating_avg' => 4.9, 'reviews_count' => 186, 'badge' => 'Romantic Pick', 'is_featured' => true, 'booking_enabled' => true,
            'cover' => 'images/yacht-windward.jpg',
            'gallery' => ['images/yacht-windward.jpg', 'images/bow.jpg', 'images/exp-couple.jpg', 'images/chicago-navy-pier.jpg'],
            'short' => 'A classic sailing yacht for couples and small groups — quiet, breezy and unforgettable at sunset.',
            'description' => 'There is nothing quite like the hush of the sails filling as the skyline glows. Windward is a beautifully kept 42-foot sailing yacht for up to six guests — perfect for proposals, anniversaries and anyone who wants to actually feel the lake.',
            'amenities' => ['Under-sail experience', 'Cushioned cockpit seating', 'Private cabin', '1 restroom', 'Bluetooth speaker', 'Blankets for evening sails', 'Cooler with ice', 'Hands-on sailing (optional)'],
            'experiences' => ['sunset', 'proposal', 'fireworks', 'private'],
        ],
        [
            'slug' => 'golden', 'title' => 'Golden Hour', 'category' => 'Motor Yacht', 'length_ft' => 58, 'max_guests' => 13, 'staterooms' => 3, 'heads' => 2, 'year' => 2022,
            'harbor' => 'dusable', 'hourly_rate_cents' => 39500, 'min_hours' => 3, 'captain_rate_cents' => 8500, 'crew_count' => 1, 'cleaning_fee_cents' => 15000,
            'rating_avg' => 4.8, 'reviews_count' => 97, 'badge' => 'New in 2022', 'is_featured' => false, 'booking_enabled' => true,
            'cover' => 'images/yacht-golden-hour.jpg',
            'gallery' => ['images/yacht-golden-hour.jpg', 'images/cockpit.jpg', 'images/deck-lounge.jpg', 'images/exp-friends.jpg'],
            'short' => 'Modern lines, warm teak and a spacious sun pad — a stylish pick for birthdays and river cruises.',
            'description' => 'Golden Hour is our newest 13-guest yacht, with a contemporary interior, a large forward sun pad and an easy-boarding swim platform. Low enough for the Chicago River and roomy enough for a proper party on the lake.',
            'amenities' => ['Forward sun pad', 'Teak swim platform', 'Modern salon', '2 restrooms', 'Premium sound', 'Coolers & ice', 'River-cruise friendly', 'USB charging'],
            'experiences' => ['sunset', 'playpen', 'river', 'fireworks', 'celebration', 'proposal', 'private'],
        ],
        [
            'slug' => 'lakeside', 'title' => 'Lakeside Party Cat', 'category' => 'Catamaran', 'length_ft' => 65, 'max_guests' => 80, 'staterooms' => 0, 'heads' => 2, 'year' => 2020,
            'harbor' => 'navypier', 'hourly_rate_cents' => 125000, 'min_hours' => 3, 'captain_rate_cents' => 22500, 'crew_count' => 4, 'cleaning_fee_cents' => 30000,
            'rating_avg' => 4.9, 'reviews_count' => 241, 'badge' => 'Party Favorite', 'is_featured' => false, 'booking_enabled' => true,
            'cover' => 'images/yacht-party-cat.jpg',
            'gallery' => ['images/yacht-party-cat.jpg', 'images/exp-party.jpg', 'images/exp-toast.jpg', 'images/exp-swim.jpg'],
            'short' => 'A wide, stable party catamaran for up to 80 guests with a DJ booth, bar counter and huge open deck.',
            'description' => 'When the guest list gets big, the Lakeside Party Cat delivers. Two hulls mean a smooth, stable ride and an enormous open deck — ideal for company summer parties, reunions and Playpen days with the whole crew.',
            'amenities' => ['Huge open deck', 'DJ booth & PA system', 'Bar counter', '2 restrooms', 'Shaded lounge', 'Swim ladders', 'Captain + 3 crew', 'ADA-friendly boarding'],
            'experiences' => ['playpen', 'celebration', 'corporate', 'fireworks', 'private'],
        ],
        [
            'slug' => 'onyx', 'title' => 'Midnight Onyx', 'category' => 'Superyacht', 'length_ft' => 85, 'max_guests' => 49, 'staterooms' => 5, 'heads' => 4, 'year' => 2023,
            'harbor' => 'burnham', 'hourly_rate_cents' => 165000, 'min_hours' => 3, 'captain_rate_cents' => 27500, 'crew_count' => 4, 'cleaning_fee_cents' => 35000,
            'rating_avg' => 5.0, 'reviews_count' => 64, 'badge' => 'Flagship', 'is_featured' => false, 'booking_enabled' => true,
            'cover' => 'images/yacht-midnight-onyx.jpg',
            'gallery' => ['images/yacht-midnight-onyx.jpg', 'images/superyacht.jpg', 'images/interior-salon.jpg', 'images/deck-aft.jpg', 'images/wake.jpg'],
            'short' => 'Our flagship 85-foot superyacht with a dedicated steward — the ultimate Chicago VIP experience.',
            'description' => 'Midnight Onyx is the most refined way to see Chicago from the water. A full crew including a dedicated steward, a formal dining salon, and a sky lounge built for sunset cocktails and front-row fireworks views.',
            'amenities' => ['Sky lounge', 'Formal dining salon', 'Dedicated steward', '4 restrooms', 'Jacuzzi on deck', 'Surround sound', 'Captain + 3 crew', 'Concierge catering'],
            'experiences' => ['sunset', 'fireworks', 'corporate', 'proposal', 'celebration', 'private'],
        ],
    ],

    // allowed_weekdays: 0 = Sun … 6 = Sat. null = every day.
    'experiences' => [
        [
            'slug' => 'sunset', 'name' => 'Sunset Skyline Cruise', 'category' => 'sightseeing', 'icon' => 'sunset', 'image' => 'images/hero-poster.jpg',
            'tagline' => 'Golden hour over Lake Michigan',
            'description' => 'Watch the city light up as the sun drops behind the skyline. Cruise past Navy Pier, the Lakefront and Museum Campus with a drink in hand and the best seat in Chicago.',
            'includes' => ['Golden-hour departure', 'Skyline photo stop', 'Ice, water & cups', 'Bluetooth playlist'],
            'durations' => [2, 3, 4], 'time_slots' => ['17:30', '18:00', '18:30', '19:00'], 'package_fee_cents' => 0, 'allowed_weekdays' => null,
        ],
        [
            'slug' => 'playpen', 'name' => 'Playpen Swim Party', 'category' => 'party', 'icon' => 'waves', 'image' => 'images/exp-swim.jpg',
            'tagline' => "Anchor up at Chicago's favorite floating party",
            'description' => 'Drop anchor at the Playpen, the sheltered swim spot off Ohio Street Beach. Float on the lily pad, swim off the back of the boat and soak up the summer energy.',
            'includes' => ['Anchor at the Playpen', 'Lily pad & pool floats', 'Swim ladder', 'Towels on request'],
            'durations' => [3, 4, 5], 'time_slots' => ['11:00', '12:00', '13:00', '14:00'], 'package_fee_cents' => 15000, 'allowed_weekdays' => null,
        ],
        [
            'slug' => 'river', 'name' => 'River Architecture Cruise', 'category' => 'sightseeing', 'icon' => 'building-2', 'image' => 'images/chicago-river-bridges.jpg',
            'tagline' => "Glide past Chicago's iconic architecture",
            'description' => 'Wind through the canyon of skyscrapers on the Chicago River — Marina City, the Wrigley Building and the Riverwalk — then head out to the lake for open-water views.',
            'includes' => ['Main & south branch route', 'Captain-led highlights', 'Lakefront lock passage', 'Ice & water'],
            'durations' => [2, 3], 'time_slots' => ['10:00', '13:00', '16:00'], 'package_fee_cents' => 7500, 'allowed_weekdays' => null,
        ],
        [
            'slug' => 'fireworks', 'name' => 'Navy Pier Fireworks', 'category' => 'sightseeing', 'icon' => 'sparkles', 'image' => 'images/exp-fireworks.jpg',
            'tagline' => 'Front-row seats to the summer fireworks',
            'description' => 'Anchor just off Navy Pier for a private, front-row view of the summer fireworks show, then cruise home past a glittering skyline. Available on Wednesday and Saturday nights.',
            'includes' => ['Prime anchoring spot', 'Night-cruise lighting', 'Blankets & ice', 'Skyline night cruise'],
            'durations' => [2, 3], 'time_slots' => ['20:00', '20:30'], 'package_fee_cents' => 25000, 'allowed_weekdays' => [3, 6],
        ],
        [
            'slug' => 'celebration', 'name' => 'Birthdays & Bachelorettes', 'category' => 'party', 'icon' => 'party-popper', 'image' => 'images/exp-friends.jpg',
            'tagline' => 'Celebrate the big day on the water',
            'description' => "Birthdays, bachelorettes, graduations, reunions — we'll set the scene with décor, a curated playlist and a route that hits the Playpen or the skyline at sunset.",
            'includes' => ['Welcome décor & banner', 'Celebration playlist', 'Champagne flutes', 'Photo stop'],
            'durations' => [3, 4], 'time_slots' => ['11:00', '15:00', '19:00'], 'package_fee_cents' => 17500, 'allowed_weekdays' => null,
        ],
        [
            'slug' => 'corporate', 'name' => 'Corporate Outings', 'category' => 'private', 'icon' => 'briefcase', 'image' => 'images/exp-toast.jpg',
            'tagline' => 'Team events, client entertaining & offsites',
            'description' => 'Impress clients or reward your team with a private charter. We coordinate catering, branded touches and invoicing, and our crew keeps everything running on schedule.',
            'includes' => ['Dedicated event coordinator', 'Catering coordination', 'Mic & AV on larger yachts', 'Invoice & W-9 on request'],
            'durations' => [3, 4, 6], 'time_slots' => ['09:00', '12:00', '15:00', '18:00'], 'package_fee_cents' => 0, 'allowed_weekdays' => null,
        ],
        [
            'slug' => 'proposal', 'name' => 'Proposals & Romance', 'category' => 'private', 'icon' => 'heart', 'image' => 'images/exp-couple.jpg',
            'tagline' => 'The yes you will remember forever',
            'description' => 'A private sunset sail with roses, champagne and a captain who knows exactly when to look the other way. We help plan every detail — including a hidden photographer.',
            'includes' => ['Rose petals & candles', 'Chilled champagne', 'Private sunset route', 'Planning call with our team'],
            'durations' => [2, 3], 'time_slots' => ['17:30', '18:30'], 'package_fee_cents' => 29500, 'allowed_weekdays' => null,
        ],
        [
            'slug' => 'private', 'name' => 'Private Day Charter', 'category' => 'private', 'icon' => 'ship-wheel', 'image' => 'images/yacht-aerial.jpg',
            'tagline' => 'Your yacht, your route, your rules',
            'description' => 'Want the lake your way? Book a fully private charter and tell your captain where to go — skyline, beaches, the Playpen or simply open water.',
            'includes' => ['Fully custom route', 'Flexible stops', 'Ice & water', 'Bluetooth sound'],
            'durations' => [2, 3, 4, 6, 8], 'time_slots' => ['09:00', '12:00', '15:00', '18:00'], 'package_fee_cents' => 0, 'allowed_weekdays' => null,
        ],
    ],

    // unit: 'flat' = per booking, 'per_guest' = price × guests
    'addons' => [
        ['slug' => 'catering', 'name' => 'Chef-curated catering', 'description' => 'Charcuterie, sliders, fruit & dessert bites', 'price_cents' => 3500, 'unit' => 'per_guest', 'icon' => 'utensils'],
        ['slug' => 'champagne', 'name' => 'Champagne toast', 'description' => 'Two chilled bottles of brut + crystal flutes', 'price_cents' => 12000, 'unit' => 'flat', 'icon' => 'wine'],
        ['slug' => 'decor', 'name' => 'Celebration décor', 'description' => 'Balloons, banner & styled table', 'price_cents' => 17500, 'unit' => 'flat', 'icon' => 'party-popper'],
        ['slug' => 'lilypad', 'name' => 'Lily pad & water toys', 'description' => '18 ft floating mat, noodles & tubes', 'price_cents' => 15000, 'unit' => 'flat', 'icon' => 'waves'],
        ['slug' => 'photographer', 'name' => 'Pro photographer', 'description' => '1 hour on board + edited gallery in 72 hrs', 'price_cents' => 35000, 'unit' => 'flat', 'icon' => 'camera'],
        ['slug' => 'dj', 'name' => 'Live DJ', 'description' => 'Professional DJ with lights & sound', 'price_cents' => 45000, 'unit' => 'flat', 'icon' => 'music'],
        ['slug' => 'ice', 'name' => 'Cooler & ice package', 'description' => 'Two coolers stocked with ice & water', 'price_cents' => 4500, 'unit' => 'flat', 'icon' => 'snowflake'],
    ],

    // Site content the admin will edit in Phase 10 (testimonials, faqs, … tables).
    'content' => [
        'stats' => [
            ['value' => 25, 'suffix' => 'K+', 'label' => 'Guests hosted'],
            ['value' => 4800, 'suffix' => '+', 'label' => 'Charters'],
            ['value' => 20, 'suffix' => '+', 'label' => 'Licensed captains'],
            ['value' => 4, 'suffix' => '', 'label' => 'Chicago harbors'],
        ],
        'rating' => ['value' => 4.9, 'count' => '1,200+'],
        'trusted_by' => ['Lakeview Partners', 'Northwind Group', 'Loop Capital Co.', 'Riverside Health', 'Union Station Labs', 'Wacker & Co.'],
        'testimonials' => [
            ['name' => 'Daniel R.', 'trip' => 'Birthday · Playpen Swim Party', 'rating' => 5, 'featured' => false, 'quote' => "Booked The Azure for my wife's 40th and it was flawless. Captain Mike knew exactly where to anchor at the Playpen and the décor was set up when we arrived."],
            ['name' => 'Aisha P.', 'trip' => 'Corporate Outing', 'rating' => 5, 'featured' => true, 'quote' => 'We hosted 45 clients on Skyline Majesty. The booking was easy, the invoice was clean, and the crew made us look like rock stars. Already booked next summer.'],
            ['name' => 'Jordan T.', 'trip' => 'Proposal · Sunset Sail', 'rating' => 5, 'featured' => false, 'quote' => 'I proposed on Windward at sunset. The team helped me plan everything — rose petals, champagne, even a hidden photographer. She said yes!'],
            ['name' => 'Maria L.', 'trip' => 'Navy Pier Fireworks', 'rating' => 5, 'featured' => false, 'quote' => 'The fireworks cruise was the highlight of our Chicago trip. Front-row view, no crowds, and the skyline on the way back was unreal.'],
            ['name' => 'Sophie K.', 'trip' => 'Bachelorette', 'rating' => 5, 'featured' => false, 'quote' => 'Bachelorette party for 12 on Golden Hour. Our captain was so fun and the whole thing felt VIP. Best decision of the weekend.'],
            ['name' => 'Chris W.', 'trip' => 'River Architecture Cruise', 'rating' => 5, 'featured' => false, 'quote' => 'Transparent pricing was a breath of fresh air — what we saw at checkout is exactly what we paid. The river architecture tour was fascinating.'],
        ],
        'gallery' => [
            ['image' => 'images/chicago-river-night.jpg', 'alt' => 'Chicago River at night'],
            ['image' => 'images/exp-toast.jpg', 'alt' => 'Guests toasting on a yacht'],
            ['image' => 'images/bow.jpg', 'alt' => 'Woman standing on a yacht bow'],
            ['image' => 'images/yacht-aerial.jpg', 'alt' => 'Aerial view of a white yacht'],
            ['image' => 'images/chicago-river-cruise.jpg', 'alt' => 'Boat cruising the Chicago River'],
            ['image' => 'images/fireworks.jpg', 'alt' => 'Fireworks over the water'],
            ['image' => 'images/interior-salon.jpg', 'alt' => 'Yacht salon interior'],
            ['image' => 'images/exp-couple.jpg', 'alt' => 'Couple toasting on a yacht'],
        ],
        // About page crew (Phase 8: public captain profiles from the captains table).
        'team' => [
            ['name' => 'Capt. Mike R.', 'initials' => 'MR', 'role' => 'Founder · 100-ton Master', 'bio' => '18 seasons on Lake Michigan. Knows the Playpen like his backyard.'],
            ['name' => 'Capt. Sarah L.', 'initials' => 'SL', 'role' => 'Sailing Lead · OUPV', 'bio' => 'Former racing sailor and our go-to captain for proposals.'],
            ['name' => 'Capt. Devon J.', 'initials' => 'DJ', 'role' => 'Senior Captain · 50-ton', 'bio' => 'River architecture buff — ask him about every bridge.'],
            ['name' => 'Ana K.', 'initials' => 'AK', 'role' => 'Events & Guest Experience', 'bio' => 'Plans corporate events, weddings and every little surprise.'],
        ],
        'quick_answers' => [
            ['question' => 'Is the captain included?', 'answer' => 'Yes — every package includes a licensed captain, shown as its own line in your quote.'],
            ['question' => 'Can I change my date?', 'answer' => 'Date changes are free up to 14 days before your charter, subject to availability.'],
            ['question' => 'Do you offer gift cards?', 'answer' => 'Gift cards are coming soon. Until then, call us and we will set up a custom gift package.'],
        ],
        'faqs' => [
            ['question' => 'Is the captain included in the price?', 'answer' => "Yes. Every package includes a licensed USCG captain (and crew on larger yachts). The captain fee is shown as its own line in your quote so you always know exactly what you're paying for."],
            ['question' => 'Can we bring our own food and drinks?', 'answer' => "Absolutely — most charters are BYOB (21+) and you're welcome to bring food. No glass bottles please. You can also add chef-curated catering or a champagne toast during booking."],
            ['question' => 'What happens if the weather is bad?', 'answer' => 'Safety comes first. If your captain cancels due to weather, you can reschedule for free or receive a full refund of everything you paid.'],
            ['question' => 'How much do I pay upfront?', 'answer' => 'A 50% deposit secures your date. The remaining balance is charged automatically 7 days before your charter. You can also choose to pay in full at checkout.'],
            ['question' => 'Where do we board?', 'answer' => "Each yacht departs from one of four harbors: DuSable Harbor, Navy Pier, Belmont Harbor or Burnham Harbor. Your confirmation includes the exact dock, parking tips and your captain's phone number."],
            ['question' => 'How far in advance should I book?', 'answer' => "Summer weekends and fireworks nights often sell out 4–6 weeks ahead. Weekdays are usually available with a few days' notice."],
        ],
    ],

    // Settings that move to the `settings` table in Phase 3.
    'pricing' => [
        'weekend_multiplier_bp' => 11500, // Fri, Sat, Sun
        'tax_rate_bp' => 900,
        'deposit_bp' => 5000,
        'balance_due_days' => 7,
        'gratuity_options' => [0, 15, 18, 20],
        'default_gratuity' => 18,
        'promos' => [
            ['code' => 'WELCOME10', 'type' => 'percent', 'value' => 10, 'applies_to' => 'charter', 'label' => '10% off yacht charter'],
            ['code' => 'SUNSET50', 'type' => 'fixed', 'value' => 5000, 'applies_to' => 'subtotal', 'label' => '$50 off your booking'],
        ],
    ],

];
