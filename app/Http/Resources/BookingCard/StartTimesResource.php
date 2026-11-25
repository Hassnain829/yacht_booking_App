<?php

namespace App\Http\Resources\BookingCard;

use App\Http\Resources\ApiResource;
use Illuminate\Http\Request;

/**
 * One day's start times on the booking card (GET /yachts/{slug}/availability?date=…): each time, free or taken,
 * with a short reason a guest can read. Never the other trip's details.
 */
class StartTimesResource extends ApiResource
{
    private const REASONS = [
        'notice' => 'Too soon',
        'booked' => 'Booked',
        'blocked' => 'Not available',
        'one_a_day' => 'Booked that day',
    ];

    /**
     * @return array{date: string, slots: list<array{time: string, label: string, available: bool, reason: ?string}>}
     */
    public function toArray(Request $request): array
    {
        return [
            'date' => (string) $this->resource['date'],
            'slots' => array_map(fn (array $slot) => [
                'time' => $slot['time'],
                'label' => $slot['label'],
                'available' => $slot['available'],
                'reason' => $slot['reason'] === null ? null : (self::REASONS[$slot['reason']] ?? 'Not available'),
            ], $this->resource['slots']),
        ];
    }
}
