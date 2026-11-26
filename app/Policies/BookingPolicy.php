<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Bookings (PLAN §8.2 rule 3): a guest sees and pays only their own. Anyone else gets a 404, never a 403, so a
 * booking's existence isn't given away. Admin screens (4.8) use their own gate.
 */
class BookingPolicy
{
    public function view(User $user, Booking $booking): Response
    {
        return $booking->customer_id !== null && $booking->customer_id === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** Pay, change the add-ons or the promo code: the guest's own booking (open or not; the actions check that). */
    public function pay(User $user, Booking $booking): Response
    {
        return $this->view($user, $booking);
    }
}
