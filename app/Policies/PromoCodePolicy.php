<?php

namespace App\Policies;

/** Promo codes (PLAN 4.8): managed by the admin only, with the rest of the catalog (`manage-catalog`). */
class PromoCodePolicy extends ManagedRecordPolicy
{
    protected function ability(): string
    {
        return 'manage-catalog';
    }
}
