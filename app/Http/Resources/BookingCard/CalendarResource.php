<?php

namespace App\Http\Resources\BookingCard;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * A month on the booking card's calendar (GET /yachts/{slug}/availability?month=…): each date's status only —
 * past, unavailable, soldout, limited or available (AvailabilityService::calendar). Never who booked what.
 */
class CalendarResource extends ApiResource
{
    /**
     * @return array{month: string, days: array<string, string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'month' => (string) $this->resource['month'],
            'days' => $this->resource['days'],
        ];
    }
}
