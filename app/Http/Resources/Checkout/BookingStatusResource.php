<?php

namespace App\Http\Resources\Checkout;

use App\Enums\BookingStatus;
use App\Http\Resources\ApiResource;
use App\Models\Booking;
use Illuminate\Http\Request;

/**
 * Where a booking stands after the card step (GET /checkout/{booking}/status, polled by the "Confirming your
 * payment" page): its status, payment status and, once confirmed, the confirmation page. Nothing else.
 */
class BookingStatusResource extends ApiResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Booking $booking */
        $booking = $this->resource;
        $failure = $booking->payments()->latest('id')->value('failure_message');

        return [
            'booking' => $booking->ulid,
            'status' => $booking->status->value,
            'payment_status' => $booking->payment_status->value,
            'payment_failure' => $booking->status === BookingStatus::Hold ? $failure : null,
            'confirmation_url' => $booking->status === BookingStatus::Confirmed ? route('bookings.confirmation', $booking) : null,
            'checkout_url' => $booking->status === BookingStatus::Hold ? route('checkout.show', $booking) : null,
        ];
    }
}
