<?php

/*
|--------------------------------------------------------------------------
| Demo customer account (Phase 1 only)
|--------------------------------------------------------------------------
|
| One sample guest for the account screens (/account, /account/trips). Read through
| App\Support\Demo\DemoCustomer: day offsets become dates relative to today (Chicago) and
| prices come from DemoQuote, like a real booking's price snapshot.
| Phase 2/4 replaces this with the users / bookings tables; then this file is deleted.
|
*/

return [

    'profile' => [
        'first_name' => 'Alex',
        'last_name' => 'Morgan',
        'email' => 'alex@example.com',
        'phone' => '(312) 555-0100',
        'member_since_days' => -200,
        'marketing' => true,
        'password_changed_days' => -95,
    ],

    // Display cache of Stripe payment methods (PLAN §6.6) — never full card numbers.
    'cards' => [
        ['id' => 'pm_demo_visa', 'brand' => 'Visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2028, 'default' => true],
        ['id' => 'pm_demo_mc', 'brand' => 'Mastercard', 'last4' => '4444', 'exp_month' => 8, 'exp_year' => 2027, 'default' => false],
    ],

    'captains' => [
        'devon' => ['name' => 'Capt. Devon J.', 'initials' => 'DJ', 'license' => 'USCG Master · 50-ton', 'years' => 14],
        'maria' => ['name' => 'Capt. Maria L.', 'initials' => 'ML', 'license' => 'USCG Master · 100-ton', 'years' => 9],
    ],

    // `policy` = the cancellation policy snapshot taken at booking time (PLAN §7.4).
    'trips' => [
        ['ulid' => '01JDTR1PQ7M3K8R2V5X9C4B6NA', 'ref' => 'CYB-8M2K4Q', 'yacht' => 'azure', 'experience' => 'sunset', 'day' => 12, 'time' => '18:00', 'hours' => 3, 'guests' => 8, 'addons' => ['champagne'], 'gratuity' => 18, 'plan' => 'deposit', 'status' => 'confirmed', 'policy' => 'moderate', 'captain' => 'devon', 'card' => 'Visa •••• 4242', 'occasion' => 'Birthday'],
        ['ulid' => '01JDTR1PQ7M3K8R2V5X9C4B6NB', 'ref' => 'CYB-5T9W3R', 'yacht' => 'lakeside', 'experience' => 'celebration', 'day' => 45, 'time' => '15:00', 'hours' => 4, 'guests' => 30, 'addons' => ['dj', 'decor'], 'gratuity' => 18, 'plan' => 'deposit', 'status' => 'confirmed', 'policy' => 'strict', 'captain' => null, 'card' => 'Visa •••• 4242', 'occasion' => 'Corporate event'],
        ['ulid' => '01JDTR1PQ7M3K8R2V5X9C4B6NC', 'ref' => 'CYB-2K7D9V', 'yacht' => 'windward', 'experience' => 'sunset', 'day' => -2, 'time' => '17:30', 'hours' => 2, 'guests' => 4, 'addons' => [], 'gratuity' => 20, 'plan' => 'full', 'status' => 'completed', 'policy' => 'flexible', 'captain' => 'maria', 'card' => 'Mastercard •••• 4444', 'occasion' => 'Anniversary'],
        ['ulid' => '01JDTR1PQ7M3K8R2V5X9C4B6ND', 'ref' => 'CYB-6Q4H8M', 'yacht' => 'golden', 'experience' => 'river', 'day' => -34, 'time' => '13:00', 'hours' => 3, 'guests' => 6, 'addons' => [], 'gratuity' => 18, 'plan' => 'full', 'status' => 'completed', 'policy' => 'moderate', 'captain' => 'devon', 'card' => 'Visa •••• 4242', 'occasion' => null],
        ['ulid' => '01JDTR1PQ7M3K8R2V5X9C4B6NE', 'ref' => 'CYB-3W8N2J', 'yacht' => 'skyline', 'experience' => 'private', 'day' => -20, 'time' => '12:00', 'hours' => 4, 'guests' => 20, 'addons' => [], 'gratuity' => 18, 'plan' => 'deposit', 'status' => 'cancelled', 'policy' => 'flexible', 'captain' => null, 'card' => 'Visa •••• 4242', 'occasion' => null, 'cancelled_days' => -31],
    ],

];
