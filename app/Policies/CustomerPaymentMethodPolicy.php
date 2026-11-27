<?php

namespace App\Policies;

use App\Models\CustomerPaymentMethod;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Saved cards (5.3): a guest manages only their own. Anyone else's card is a 404 (PLAN §8.2 rule 3). */
class CustomerPaymentMethodPolicy
{
    public function manage(User $user, CustomerPaymentMethod $card): Response
    {
        return $card->user_id === $user->getKey() ? Response::allow() : Response::denyAsNotFound();
    }
}
