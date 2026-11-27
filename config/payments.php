<?php

/*
| Card payments (PLAN §6.6, §8.4). Every booking is paid in full at checkout (D22; taken from 4.5, user decision
| 2026-11-26).
|
| driver: "stripe" (real, test or live keys) or "fake" (local development and tests only — a test card form, no
| money moves; refused in production). With Stripe keys in .env the driver is Stripe unless PAYMENTS_DRIVER says
| otherwise.
|
| Refunds (5.4, D23): a guest's cancellation only asks for one; the admin approves it before money moves. The card
| processing fee is not refunded.
*/

return [
    'driver' => env('PAYMENTS_DRIVER', env('STRIPE_SECRET') ? 'stripe' : 'fake'),

    'currency' => 'usd',

    // The card processing fee, used when the provider hasn't reported the real one (the fake gateway, or a Stripe
    // payment whose balance transaction isn't known yet). It is kept back from refunds: the guest bears it (D23).
    'card_fee' => [
        'percent_bp' => (int) env('CARD_FEE_PERCENT_BP', 290), // 2.9%
        'fixed_cents' => (int) env('CARD_FEE_FIXED_CENTS', 30),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'), // publishable (pk_test_… / pk_live_…), shown to the browser
        'secret' => env('STRIPE_SECRET'), // sk_test_… / sk_live_…, server only
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'), // whsec_…, from the Stripe dashboard or `stripe listen`
    ],
];
