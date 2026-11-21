<?php

/*
|--------------------------------------------------------------------------
| "List Your Boat" wizard options
|--------------------------------------------------------------------------
|
| Ported from the mockup (data.js → listing). The wizard and the server read the same lists,
| so a value is only accepted if it is in here (Phase 3: ListingRequest uses Rule::in()).
| Phase 3/10: lists the admin should edit (features, makes…) can move to lookup tables.
|
*/

return [

    'steps' => [
        'Boat details (4 boat photos minimum)',
        'Availability',
        'Operators, durations and pricing',
        'Insurance information if applicable',
        'Review and submit',
    ],

    'location_types' => ['Marina slip', 'Marina dry storage', 'Marina rack storage', 'Marina mooring', 'Residence trailer', 'Residence slip', 'Residence mooring', 'Other'],

    'countries' => ['United States', 'Canada'],

    'states' => ['AL', 'AK', 'AZ', 'AR', 'CA', 'CO', 'CT', 'DC', 'DE', 'FL', 'GA', 'HI', 'ID', 'IL', 'IN', 'IA', 'KS', 'KY', 'LA', 'ME', 'MD', 'MA', 'MI', 'MN', 'MS', 'MO', 'MT', 'NE', 'NV', 'NH', 'NJ', 'NM', 'NY', 'NC', 'ND', 'OH', 'OK', 'OR', 'PA', 'RI', 'SC', 'SD', 'TN', 'TX', 'UT', 'VT', 'VA', 'WA', 'WV', 'WI', 'WY'],

    // Same windows as PLAN §7.4 (R2).
    'cancellation' => [
        ['id' => 'flexible', 'name' => 'Flexible', 'badge' => 'Recommended', 'points' => ['Free cancellations until 24 hours before the booking start time.', 'Cancellations within 24 hours of the booking start time are non-refundable.']],
        ['id' => 'moderate', 'name' => 'Moderate', 'badge' => null, 'points' => ['Free cancellations until 5 days before the booking start time.', '50% refund for cancellations between 3–5 days before the booking start time.', 'Cancellations within 2 days of the booking start time are non-refundable.']],
        ['id' => 'strict', 'name' => 'Strict', 'badge' => null, 'points' => ['Free cancellations until 30 days before the booking start time.', '50% refund for cancellations between 15–30 days before the booking start time.', 'Cancellations within 14 days of the booking start time are non-refundable.']],
    ],

    // Refund windows per policy (PLAN §7.4, R2): [hours before the start ≥ N, refund %], longest first.
    // Phase 3: stored as data so the admin can edit hours and percentages.
    'cancellation_rules' => [
        'flexible' => [[24, 100], [0, 0]],
        'moderate' => [[120, 100], [72, 50], [0, 0]],
        'strict' => [[720, 100], [360, 50], [0, 0]],
    ],

    'rules' => ['Shoes', 'Glass bottles', 'Fishing', 'Pets', 'Kids under 12', 'Smoking', 'Alcohol', 'Liveaboard', 'Swimming', 'Red wine'],

    'features' => ['Air conditioning', 'Anchor', 'Bathroom', 'Bluetooth audio', 'Cooler / ice chest', 'Deck shower', 'Fish finder', 'Grill', 'Livewell / baitwell', 'Refrigerator', 'Rod holders', 'Shower', 'Stereo', 'Stereo AUX input', 'Swim ladder', 'WiFi'],

    'extras' => ['Lily pad / float', 'Paddleboard', 'Kayak', 'Tube', 'Water skis', 'Wakeboard', 'Snorkeling gear', 'Fishing gear'],

    'navigation' => ['GPS', 'Chartplotter', 'Depth finder', 'VHF radio', 'Radar', 'Autopilot', 'Bow thruster', 'Compass'],

    'makes' => ['Azimut', 'Bayliner', 'Beneteau', 'Boston Whaler', 'Carver', 'Catalina', 'Chaparral', 'Cobalt', 'Cruisers Yachts', 'Formula', 'Grady-White', 'Hatteras', 'Jeanneau', 'Lagoon', 'Leopard', 'Malibu', 'MasterCraft', 'Meridian', 'Prestige', 'Princess', 'Regal', 'Sea Ray', 'Sunseeker', 'Tiara', 'Viking', 'Other'],

    'types' => ['Power', 'Sail'],

    'categories' => ['Airboat', 'Aluminum fishing', 'Angler', 'Bass boat', 'Bow rider', 'Catamaran', 'Center console', 'Classic', 'Cruiser', 'Cuddy cabin', 'Daysailer', 'Deck boat', 'Dual console', 'Electric boat', 'Express cruiser', 'Flats boat', 'Flybridge', 'Houseboat', 'Jet boat', 'Mega yacht', 'Motor yacht', 'Pontoon', 'Power catamaran', 'Sailboat', 'Ski & wakeboard', 'Sport fishing', 'Trawler', 'Walkaround', 'Other'],

    'engine_types' => ['Inboard', 'Outboard', 'Inboard/Outboard (sterndrive)', 'Jet drive', 'Pod drive', 'V-drive', 'Electric'],

    'advance_notice' => [['1h', '1 hour'], ['3h', '3 hours'], ['6h', '6 hours'], ['12h', '12 hours'], ['1d', '1 day'], ['3d', '3 days'], ['7d', '7 days']],

    'buffers' => [['30m', '30 min'], ['1h', '1 hour'], ['2h', '2 hours'], ['3h', '3 hours']],

    'durations' => [2, 3, 4, 6, 8],

    'liability' => ['$300,000', '$500,000', '$1,000,000', '$2,000,000+'],

    // Mockup rules kept (PLAN §7.7).
    'rules_engine' => [
        'min_photos' => 4,
        'max_photo_mb' => 25,
        'renter_max_length_ft' => 32, // longer boats can't be operated by the renter
        'captain_required_over_ft' => 33, // longer boats must be captained (insurance)
        'title_max' => 70,
        'description_max' => 5000,
    ],

    // New Pricing step (D7). All amounts in cents; the admin can adjust them before approval.
    'pricing' => [
        'hourly_min_cents' => 5000,
        'hourly_max_cents' => 2000000,
        'fixed_price_max_cents' => 10000000,
        'cleaning_fee_max_cents' => 200000,
        'weekend_markups_bp' => [0, 1000, 1500, 2000, 2500], // 0% … 25% on Fri–Sun
        'default_weekend_markup_bp' => 1500,
    ],

    'sample_photos' => ['images/yacht-golden-hour.jpg', 'images/cockpit.jpg', 'images/deck-lounge.jpg', 'images/interior-salon.jpg'],

];
