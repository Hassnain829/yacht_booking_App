<?php

/*
| Card payments (PLAN §6.6, §8.4). Every booking is paid in full at checkout (D22; taken from 4.5, user decision
| 2026-11-26).
|
| driver: "stripe" (real, test or live keys) or "fake" (local development and tests only — a test card form, no
| money moves; refused in production). With Stripe keys in .env the driver is Stripe unless PAYMENTS_DRIVER says
| otherwise.
*/

return [
    'driver' => env('PAYMENTS_DRIVER', env('STRIPE_SECRET') ? 'stripe' : 'fake'),

    'currency' => 'usd',

    'stripe' => [
        'key' => env('STRIPE_KEY'), // publishable (pk_test_… / pk_live_…), shown to the browser
        'secret' => env('STRIPE_SECRET'), // sk_test_… / sk_live_…, server only
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'), // whsec_…, from the Stripe dashboard or `stripe listen`
    ],
];
