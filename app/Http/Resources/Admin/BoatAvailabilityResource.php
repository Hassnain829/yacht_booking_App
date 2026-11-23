<?php

namespace App\Http\Resources\Admin;

use App\Http\Resources\ApiResource;
use App\Models\Boat;
use Illuminate\Http\Request;

/** The admin's "Bookable" switch answer (PATCH /admin/boats/{boat}/bookable): the boat's id and its tag state only. */
class BoatAvailabilityResource extends ApiResource
{
    /**
     * @return array{id: string, title: string, booking_enabled: bool, unavailable_reason: ?string}
     */
    public function toArray(Request $request): array
    {
        /** @var Boat $boat */
        $boat = $this->resource;

        return [
            'id' => $this->publicId(),
            'title' => $boat->title,
            'booking_enabled' => $boat->booking_enabled,
            'unavailable_reason' => $boat->unavailable_reason,
        ];
    }
}
