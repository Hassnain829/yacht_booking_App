<?php

/*
|--------------------------------------------------------------------------
| Demo data for the admin screens (Phase 1 only)
|--------------------------------------------------------------------------
|
| Admin-only records (captains, users, inbox, payments, withdrawals, audit log). Bookings, boats,
| listings and complaints come from the other demo sources (DemoCatalog, DemoOwner, DemoCustomer),
| combined by App\Support\Demo\DemoAdmin. Day offsets are relative to today (Chicago).
| Phase 3+ replaces all of this with the database; then this file is deleted.
|
*/

return [

    'admin' => ['name' => 'Sam Rivera', 'initials' => 'SR', 'email' => 'admin@chicagoyachtbooking.com'],

    // captains (PLAN §6.4). license_type: master_100 | master_50 | oupv (six-pack, max 6 passengers).
    'captains' => [
        ['id' => 'devon', 'name' => 'Capt. Devon J.', 'email' => 'devon.j@example.com', 'phone' => '(312) 555-0181', 'license_type' => 'master_50', 'license_number' => 'MML-4471902', 'license_expires_days' => 410, 'rate_cents' => 8500, 'active' => true, 'trips_month' => 11],
        ['id' => 'maria', 'name' => 'Capt. Maria L.', 'email' => 'maria.l@example.com', 'phone' => '(312) 555-0172', 'license_type' => 'master_100', 'license_number' => 'MML-3390117', 'license_expires_days' => 215, 'rate_cents' => 9500, 'active' => true, 'trips_month' => 9],
        ['id' => 'luis', 'name' => 'Capt. Luis P.', 'email' => 'luis.p@example.com', 'phone' => '(773) 555-0144', 'license_type' => 'oupv', 'license_number' => 'OUPV-2218835', 'license_expires_days' => 24, 'rate_cents' => 6500, 'active' => true, 'trips_month' => 4],
        ['id' => 'grace', 'name' => 'Capt. Grace K.', 'email' => 'grace.k@example.com', 'phone' => '(312) 555-0119', 'license_type' => 'master_100', 'license_number' => 'MML-5502764', 'license_expires_days' => 600, 'rate_cents' => 9500, 'active' => false, 'trips_month' => 0],
    ],

    'license_types' => [
        'master_100' => ['label' => 'USCG Master · 100-ton', 'max_passengers' => 150],
        'master_50' => ['label' => 'USCG Master · 50-ton', 'max_passengers' => 150],
        'oupv' => ['label' => 'OUPV "six-pack"', 'max_passengers' => 6],
    ],

    'users' => [
        ['name' => 'Alex Morgan', 'email' => 'alex@example.com', 'role' => 'customer', 'joined_days' => -200, 'bookings' => 5, 'status' => 'active', 'two_factor' => false],
        ['name' => 'Marcus Reed', 'email' => 'marcus.reed@example.com', 'role' => 'owner', 'joined_days' => -420, 'bookings' => 0, 'status' => 'active', 'two_factor' => true],
        ['name' => 'Dana Fox', 'email' => 'dana.fox@example.com', 'role' => 'owner', 'joined_days' => -160, 'bookings' => 1, 'status' => 'active', 'two_factor' => true],
        ['name' => 'Jessica Moore', 'email' => 'jessica.m@example.com', 'role' => 'customer', 'joined_days' => -20, 'bookings' => 1, 'status' => 'active', 'two_factor' => false],
        ['name' => 'Ryan Kim', 'email' => 'ryan.kim@example.com', 'role' => 'customer', 'joined_days' => -12, 'bookings' => 1, 'status' => 'active', 'two_factor' => false],
        ['name' => 'Alicia Ramos', 'email' => 'alicia.r@example.com', 'role' => 'customer', 'joined_days' => -40, 'bookings' => 2, 'status' => 'active', 'two_factor' => false],
        ['name' => 'Kevin Ortiz', 'email' => 'kevin.o@example.com', 'role' => 'customer', 'joined_days' => -30, 'bookings' => 1, 'status' => 'suspended', 'two_factor' => false],
        ['name' => 'Sam Rivera', 'email' => 'admin@chicagoyachtbooking.com', 'role' => 'admin', 'joined_days' => -500, 'bookings' => 0, 'status' => 'active', 'two_factor' => true],
    ],

    // contact_messages (PLAN §6.7)
    'messages' => [
        ['name' => 'Priya Shah', 'email' => 'priya.s@example.com', 'subject' => 'Company outing for 35 people in August', 'experience' => 'Corporate Outings', 'guests' => '26–49', 'status' => 'new', 'hours_ago' => 2],
        ['name' => 'Tom Hughes', 'email' => 'tom.h@example.com', 'subject' => 'Can we bring a birthday cake on board?', 'experience' => 'Sunset Skyline Cruise', 'guests' => '1–6', 'status' => 'new', 'hours_ago' => 5],
        ['name' => 'Lena Novak', 'email' => 'lena.n@example.com', 'subject' => 'Proposal on the 4th of July?', 'experience' => 'Proposals & Romance', 'guests' => '1–6', 'status' => 'open', 'hours_ago' => 26],
        ['name' => 'Chris Park', 'email' => 'chris.p@example.com', 'subject' => 'Gift card for my parents', 'experience' => null, 'guests' => null, 'status' => 'replied', 'hours_ago' => 70],
        ['name' => 'Spam Bot', 'email' => 'win@prize.example', 'subject' => 'You have won!!!', 'experience' => null, 'guests' => null, 'status' => 'spam', 'hours_ago' => 90],
    ],

    // Owner withdrawal requests waiting for the admin (payouts.status = requested | on_hold).
    'withdrawals' => [
        ['ref' => 'PO-9C4T2X', 'owner' => 'Marcus Reed', 'boat' => 'Lady Grace', 'amount_cents' => 185000, 'available_cents' => 221800, 'requested_hours_ago' => 20, 'status' => 'requested', 'method' => 'Chase •••• 6789', 'open_complaints' => 1],
        ['ref' => 'PO-5H8N1Q', 'owner' => 'Dana Fox', 'boat' => 'Silver Tide', 'amount_cents' => 64000, 'available_cents' => 64000, 'requested_hours_ago' => 75, 'status' => 'on_hold', 'method' => 'Bank of America •••• 1180', 'open_complaints' => 0, 'note' => 'Waiting for an updated insurance certificate.'],
    ],

    // payments (PLAN §6.6) — the newest few, for the Finance screen.
    'payments' => [
        ['ref' => 'CYB-8M2K4Q', 'customer' => 'Alex Morgan', 'kind' => 'deposit', 'amount_cents' => 116633, 'status' => 'succeeded', 'card' => 'Visa •••• 4242', 'hours_ago' => 30],
        ['ref' => 'CYB-K8D2PZ', 'customer' => 'Ryan Kim', 'kind' => 'deposit', 'amount_cents' => 141810, 'status' => 'succeeded', 'card' => 'Amex •••• 1005', 'hours_ago' => 52],
        ['ref' => 'CYB-7Q2M4K', 'customer' => 'Jessica Moore', 'kind' => 'balance', 'amount_cents' => 98550, 'status' => 'failed', 'card' => 'Visa •••• 0341', 'hours_ago' => 8, 'failure' => 'Card declined (insufficient funds)'],
        ['ref' => 'CYB-3W8N2J', 'customer' => 'Alex Morgan', 'kind' => 'deposit', 'amount_cents' => 287600, 'status' => 'refunded', 'card' => 'Visa •••• 4242', 'hours_ago' => 270],
        ['ref' => 'CYB-B5N7XC', 'customer' => 'Tom Hughes', 'kind' => 'full', 'amount_cents' => 182920, 'status' => 'succeeded', 'card' => 'Mastercard •••• 5100', 'hours_ago' => 300],
    ],

    'audit' => [
        ['actor' => 'Sam Rivera', 'action' => 'Approved withdrawal', 'subject' => 'PO-2N8K4D · Marcus Reed · $3,386.40', 'hours_ago' => 460],
        ['actor' => 'Sam Rivera', 'action' => 'Resolved complaint with a deduction', 'subject' => 'CYB-W2C5RB · $150.00 · reason: late start', 'hours_ago' => 310],
        ['actor' => 'Sam Rivera', 'action' => 'Assigned captain', 'subject' => 'CYB-7Q2M4K · Capt. Devon J.', 'hours_ago' => 50],
        ['actor' => 'Sam Rivera', 'action' => 'Changed setting', 'subject' => 'commission_bp 2000 → 2000 (confirmed)', 'hours_ago' => 26],
        ['actor' => 'System', 'action' => 'Released earnings', 'subject' => 'CYB-9TZ2KD · $1,032.00 available', 'hours_ago' => 4],
    ],

];
