<?php

namespace App\Support\Checkout;

/** The checkout page's fixed choices (PLAN 4.5): the occasion and "how did you hear about us". */
final class CheckoutOptions
{
    public const OCCASIONS = ['Birthday', 'Bachelorette / Bachelor', 'Anniversary', 'Proposal', 'Corporate event', 'Family outing', 'Other'];

    public const HEARD = ['Google', 'Instagram', 'Friend / referral', 'Returning guest', 'Other'];
}
