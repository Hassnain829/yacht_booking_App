<?php

/*
|--------------------------------------------------------------------------
| Demo owner account (Phase 1 only)
|--------------------------------------------------------------------------
|
| One sample boat owner, used by the owner screens (/owner/*) before the database exists.
| Read it through App\Support\Demo\DemoOwner, which turns the day offsets below into real dates
| (relative to today in Chicago) and calculates earnings the way PLAN §7.5–7.6 describes.
|
| Shapes follow PLAN §6: boats, bookings, booking_complaints, ledger_entries, payouts.
| Phase 3/7 replaces this with the database; then this file is deleted. Amounts are PLACEHOLDERS.
|
*/

return [

    // Settings that move to the `settings` table (PLAN §6.7, R3, R4, R7).
    'money' => [
        'commission_bp' => 2000,
        'payout_delay_hours' => 72,
        'complaint_window_hours' => 72,
        'min_withdrawal_cents' => 5000,
    ],

    'profile' => [
        'first_name' => 'Marcus',
        'last_name' => 'Reed',
        'email' => 'marcus.reed@example.com',
        'phone' => '(312) 555-0142',
        'member_since_days' => -420,
        'reviews_count' => 23,
        'rating_avg' => 4.9,
        'response_rate' => 98,
        'two_factor' => true,
    ],

    // Stripe Connect account (Phase 7). null = not connected yet.
    'payout_account' => ['bank' => 'Chase', 'last4' => '6789', 'connected_days' => -400],

    // Set by the admin (PLAN §7.6). null = payouts run normally.
    'payouts_paused' => null, // e.g. ['reason' => 'Insurance certificate expired', 'days' => -2]

    'boats' => [
        [
            'ulid' => '01JDQ7M3K8R2V5X9C4B6N0P1TA',
            'status' => 'approved',
            'booking_enabled' => true,
            'step' => 'review',
            'updated_days' => -12,
            'title' => 'Lady Grace — 44 ft cruiser with a sunset flybridge',
            'description' => "Lady Grace is a 2016 Sea Ray Sundancer kept in Burnham Harbor, a few minutes from the Museum Campus and the best skyline view on the lake.\n\nWide swim platform, flybridge seating for eight, a full galley and a Bluetooth sound system. Fuel is included for trips inside the breakwall.",
            'insurance_type' => 'recreational',
            'location' => ['type' => 'Marina slip', 'country' => 'United States', 'street' => '1559 S Lynn White Dr', 'city' => 'Chicago', 'state' => 'IL', 'zip' => '60605'],
            'cancellation' => 'moderate',
            'rules' => ['Shoes', 'Alcohol', 'Swimming', 'Kids under 12'],
            'features' => ['Air conditioning', 'Anchor', 'Bathroom', 'Bluetooth audio', 'Cooler / ice chest', 'Refrigerator', 'Swim ladder', 'Stereo'],
            'extras' => ['Lily pad / float', 'Paddleboard'],
            'navigation' => ['GPS', 'Chartplotter', 'Depth finder', 'VHF radio'],
            'photos' => ['images/yacht-golden-hour.jpg', 'images/deck-lounge.jpg', 'images/interior-salon.jpg', 'images/cockpit.jpg', 'images/deck-aft.jpg'],
            'specs' => ['registration' => 'IL4471SR', 'year' => '2016', 'make' => 'Sea Ray', 'model' => 'Sundancer 440', 'type' => 'Power', 'length' => '44', 'category' => 'Express cruiser', 'engines' => '2', 'horsepower' => '760', 'engine_year' => '2016', 'engine_type' => 'Inboard', 'staterooms' => '2', 'sleeps' => '6', 'beds' => '3', 'passengers' => '12'],
            'availability' => ['mode' => 'default'],
            'advance_notice' => '6h',
            'multi_booking' => ['allow' => 'yes', 'buffer' => '1h'],
            'operators' => ['renter' => false, 'captain' => true, 'captain_network' => 'yes', 'fishing_charter' => 'no'],
            'durations' => [3, 4, 6],
            'fuel' => 'owner',
            'insurance_owner' => ['years' => '6', 'company' => 'Reed Marine Charters LLC', 'business' => 'yes'],
            'insurance_boat' => ['value' => '310000', 'underlying' => 'yes'],
            'pricing' => ['hourly_cents' => 38000, 'min_hours' => 3, 'weekend_markup_bp' => 1500, 'cleaning_fee_cents' => 12000, 'fixed' => []],
        ],
        [
            'ulid' => '01JDQ7M3K8R2V5X9C4B6N0P1TB',
            'status' => 'pending',
            'booking_enabled' => false,
            'step' => 'review',
            'updated_days' => -1,
            'title' => 'Blue Horizon — 38 ft sailing yacht',
            'description' => 'A comfortable Beneteau Oceanis for quiet sunset sails off Belmont Harbor. Captain included; up to 6 guests.',
            'insurance_type' => 'commercial',
            'location' => ['type' => 'Marina mooring', 'country' => 'United States', 'street' => '3600 N Recreation Dr', 'city' => 'Chicago', 'state' => 'IL', 'zip' => '60613'],
            'cancellation' => 'flexible',
            'rules' => ['Alcohol', 'Kids under 12'],
            'features' => ['Anchor', 'Bathroom', 'Bluetooth audio', 'Cooler / ice chest'],
            'extras' => [],
            'navigation' => ['GPS', 'VHF radio', 'Compass'],
            'photos' => ['images/yacht-windward.jpg', 'images/bow.jpg', 'images/wake.jpg', 'images/exp-couple.jpg'],
            'specs' => ['registration' => 'IL2093BH', 'year' => '2019', 'make' => 'Beneteau', 'model' => 'Oceanis 38.1', 'type' => 'Sail', 'length' => '38', 'category' => 'Sailboat', 'engines' => '1', 'horsepower' => '30', 'engine_year' => '2019', 'engine_type' => 'Inboard', 'staterooms' => '2', 'sleeps' => '4', 'beds' => '2', 'passengers' => '6'],
            'availability' => ['mode' => 'default'],
            'advance_notice' => '1d',
            'multi_booking' => ['allow' => 'no', 'buffer' => '1h'],
            'operators' => ['renter' => false, 'captain' => true, 'captain_network' => 'no', 'fishing_charter' => 'no'],
            'durations' => [2, 3, 4],
            'fuel' => 'renter',
            'commercial' => ['carrier' => 'Great Lakes Marine Mutual', 'policy' => 'GLM-448210', 'expires_days' => 240, 'liability' => '$1,000,000', 'coi' => 'blue-horizon-coi.pdf'],
            'pricing' => ['hourly_cents' => 22500, 'min_hours' => 2, 'weekend_markup_bp' => 1000, 'cleaning_fee_cents' => 7500, 'fixed' => ['4' => 85000]],
        ],
        [
            'ulid' => '01JDQ7M3K8R2V5X9C4B6N0P1TC',
            'status' => 'draft',
            'booking_enabled' => false,
            'step' => 'features',
            'updated_days' => -3,
            'title' => '',
            'description' => '',
            'insurance_type' => 'recreational',
            'location' => ['type' => 'Marina slip', 'country' => 'United States', 'street' => '401 E Randolph St', 'city' => 'Chicago', 'state' => 'IL', 'zip' => '60601'],
            'cancellation' => 'flexible',
            'rules' => [],
            'features' => [],
            'extras' => [],
            'navigation' => [],
            'photos' => [],
            'specs' => ['year' => '2021', 'make' => 'Cobalt', 'model' => 'R30', 'length' => '30'],
            'pricing' => [],
        ],
    ],

    // Bookings on the owner's boats. `day` = days from today (Chicago); customers are shown as "First L."
    'bookings' => [
        ['ref' => 'CYB-7Q2M4K', 'boat' => 0, 'experience' => 'Sunset Skyline Cruise', 'day' => 3, 'time' => '18:00', 'hours' => 3, 'guests' => 8, 'customer' => 'Jessica M.', 'status' => 'confirmed', 'captain' => 'Capt. Devon J.'],
        ['ref' => 'CYB-K8D2PZ', 'boat' => 0, 'experience' => 'Private Day Charter', 'day' => 12, 'time' => '12:00', 'hours' => 4, 'guests' => 10, 'customer' => 'Ryan K.', 'status' => 'confirmed', 'captain' => null],
        ['ref' => 'CYB-3HW9TQ', 'boat' => 0, 'experience' => 'Birthdays & Bachelorettes', 'day' => 26, 'time' => '15:00', 'hours' => 4, 'guests' => 12, 'customer' => 'Priya S.', 'status' => 'confirmed', 'captain' => null],
        ['ref' => 'CYB-B5N7XC', 'boat' => 0, 'experience' => 'Sunset Skyline Cruise', 'day' => -1, 'time' => '17:30', 'hours' => 3, 'guests' => 6, 'customer' => 'Tom H.', 'status' => 'completed', 'captain' => 'Capt. Devon J.'],
        ['ref' => 'CYB-R4J6MV', 'boat' => 0, 'experience' => 'Birthdays & Bachelorettes', 'day' => -5, 'time' => '11:00', 'hours' => 4, 'guests' => 12, 'customer' => 'Alicia R.', 'status' => 'completed', 'captain' => 'Capt. Maria L.'],
        ['ref' => 'CYB-9TZ2KD', 'boat' => 0, 'experience' => 'Sunset Skyline Cruise', 'day' => -9, 'time' => '18:00', 'hours' => 3, 'guests' => 4, 'customer' => 'Daniel W.', 'status' => 'completed', 'captain' => 'Capt. Devon J.'],
        ['ref' => 'CYB-6MQ8HN', 'boat' => 0, 'experience' => 'Birthdays & Bachelorettes', 'day' => -14, 'time' => '15:00', 'hours' => 4, 'guests' => 9, 'customer' => 'Kevin O.', 'status' => 'cancelled', 'captain' => null, 'cancelled_by' => 'customer'],
        ['ref' => 'CYB-W2C5RB', 'boat' => 0, 'experience' => 'Private Day Charter', 'day' => -16, 'time' => '15:00', 'hours' => 4, 'guests' => 9, 'customer' => 'Megan L.', 'status' => 'completed', 'captain' => 'Capt. Maria L.'],
        ['ref' => 'CYB-H7V3QS', 'boat' => 0, 'experience' => 'Proposals & Romance', 'day' => -22, 'time' => '18:30', 'hours' => 3, 'guests' => 2, 'customer' => 'Chris P.', 'status' => 'completed', 'captain' => 'Capt. Devon J.', 'payout' => 'PO-2N8K4D'],
        ['ref' => 'CYB-D9X4LT', 'boat' => 0, 'experience' => 'Private Day Charter', 'day' => -28, 'time' => '12:00', 'hours' => 6, 'guests' => 12, 'customer' => 'Sam T.', 'status' => 'completed', 'captain' => 'Capt. Maria L.', 'payout' => 'PO-2N8K4D'],
        ['ref' => 'CYB-M3P7WF', 'boat' => 0, 'experience' => 'Sunset Skyline Cruise', 'day' => -40, 'time' => '17:30', 'hours' => 3, 'guests' => 7, 'customer' => 'Olivia B.', 'status' => 'completed', 'captain' => 'Capt. Devon J.', 'payout' => 'PO-7F3Q9M'],
        ['ref' => 'CYB-Q6T8NZ', 'boat' => 0, 'experience' => 'Private Day Charter', 'day' => -47, 'time' => '12:00', 'hours' => 4, 'guests' => 11, 'customer' => 'Ethan G.', 'status' => 'completed', 'captain' => 'Capt. Maria L.', 'payout' => 'PO-7F3Q9M'],
    ],

    // booking_complaints (PLAN §6.5). `booking` = ref; days relative to today.
    'complaints' => [
        [
            'ulid' => '01JDQ8C0MPL41NT5X2R7V9B3KA', 'booking' => 'CYB-R4J6MV', 'category' => 'cleanliness', 'status' => 'open', 'opened_days' => -4, 'photos' => 2,
            'description' => "The cabin bathroom wasn't cleaned before our trip and the cooler still had old ice and cans from a previous group. Everything else was great.",
            'owner_response' => null,
        ],
        [
            'ulid' => '01JDQ8C0MPL41NT5X2R7V9B3KB', 'booking' => 'CYB-W2C5RB', 'category' => 'late_start', 'status' => 'resolved', 'opened_days' => -15, 'resolved_days' => -13, 'photos' => 0,
            'description' => 'We left the dock 35 minutes after our booked start time and nobody told us why.',
            'owner_response' => 'The fuel dock had a long line that afternoon. We should have messaged the group — sorry about that.',
            'resolution' => 'deduction', 'deduction_cents' => 15000,
            'resolution_note' => 'The trip started 35 minutes late. $150 was deducted from the owner earnings and refunded to the guest.',
        ],
        [
            'ulid' => '01JDQ8C0MPL41NT5X2R7V9B3KC', 'booking' => 'CYB-D9X4LT', 'category' => 'missing_amenities', 'status' => 'rejected', 'opened_days' => -27, 'resolved_days' => -25, 'photos' => 1,
            'description' => 'The listing shows a paddleboard but it was not on board.',
            'owner_response' => 'The paddleboard is available on request, as written in the listing. The group did not ask for it.',
            'resolution_note' => 'The listing says the paddleboard is "on request". No deduction.',
        ],
    ],

    // payouts (withdrawals). Bookings above point to the payout that paid them.
    'payouts' => [
        ['ref' => 'PO-2N8K4D', 'requested_days' => -20, 'paid_days' => -19, 'status' => 'paid'],
        ['ref' => 'PO-7F3Q9M', 'requested_days' => -36, 'paid_days' => -35, 'status' => 'paid'],
    ],

    // Calendar blocks set by the owner (boat index, day offsets, inclusive).
    'blocks' => [
        ['boat' => 0, 'from' => 8, 'to' => 9, 'reason' => 'Engine service'],
        ['boat' => 0, 'from' => 18, 'to' => 18, 'reason' => 'Personal use'],
    ],

];
