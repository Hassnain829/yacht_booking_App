<?php

namespace App\Http\Controllers;

use App\Support\Demo\DemoOwner;
use Illuminate\Http\Request;
use Illuminate\Support\Fluent;
use Illuminate\View\View;

/**
 * "List Your Boat" wizard (mockup list-your-boat.html + list-boat.js, plus the new Pricing step).
 *
 * Phase 1: the wizard runs in the browser and keeps new drafts on this device (localStorage).
 * ?listing={ulid} opens one of the owner's listings (unknown or someone else's → 404).
 * Phase 3: auth + BoatPolicy, a JSON PATCH per step (ListingRequest), photo uploads through
 * MediaService, status draft → pending → approved by the admin.
 */
class ListingController extends Controller
{
    /** Step ids used by resources/js/pages/list-boat.js (whitelist for ?step=). */
    public const STEPS = [
        'intro', 'insuranceType', 'location', 'title', 'cancellation', 'rules', 'features', 'photos', 'specs',
        'availability', 'advance', 'multi', 'operators', 'pricing',
        'insurance', 'insuranceOwner', 'insuranceBoat', 'commercial', 'review',
    ];

    public function create(Request $request, DemoOwner $owner): View
    {
        $boat = null;
        if ($request->has('listing')) {
            $ulid = $request->query('listing');
            $boat = is_string($ulid) ? $owner->boat($ulid) : null;
            abort_unless($boat, 404);
        }

        $step = $request->query('step');
        $profile = $owner->profile();

        return view('listings.create', [
            'wizard' => [
                'options' => config('listing'),
                'commissionBp' => $owner->money()['commission_bp'],
                'listing' => $boat ? $this->wizardState($boat, $owner) : null,
                'isNew' => $request->query('new') === '1',
                'step' => is_string($step) && in_array($step, self::STEPS, true) ? $step : null,
                'owner' => [
                    'first_name' => $profile->first_name,
                    'last_name' => $profile->last_name,
                    'email' => $profile->email,
                    'phone' => $profile->phone,
                ],
                'urls' => [
                    'home' => route('home'),
                    'boats' => route('owner.boats.index'),
                    'dashboard' => route('owner.dashboard'),
                    'policies' => route('pricing').'#policies',
                ],
                'images' => ['wake' => asset('images/wake.jpg'), 'hero' => asset('images/hero-poster.jpg')],
                'samplePhotos' => array_map(fn (string $path) => asset($path), config('listing.sample_photos')),
            ],
        ]);
    }

    /** One of the owner's listings, in the shape the wizard uses (money as dollar strings, like the inputs). */
    private function wizardState(Fluent $boat, DemoOwner $owner): array
    {
        $dollars = fn (?int $cents) => $cents === null ? '' : (string) ($cents / 100);
        $pricing = $boat->pricing ?? [];
        $commercial = $boat->commercial;

        return [
            'id' => $boat->ulid,
            'status' => $boat->status,
            'step' => $boat->step,
            'updated_at' => $boat->updated_at->toIso8601String(),
            'insurance_type' => $boat->insurance_type ?? '',
            'location' => $boat->location ?? [],
            'title' => $boat->title ?? '',
            'description' => $boat->description ?? '',
            'cancellation' => $boat->cancellation ?? 'flexible',
            'rules' => $boat->rules ?? [],
            'features' => $boat->features ?? [],
            'extras' => $boat->extras ?? [],
            'navigation' => $boat->navigation ?? [],
            'photos' => array_map(fn (string $path) => asset($path), $boat->photos ?? []),
            'specs' => $boat->specs ?? [],
            'availability' => ['mode' => $boat->availability['mode'] ?? 'default'],
            'advance_notice' => $boat->advance_notice ?? '1h',
            'multi_booking' => $boat->multi_booking ?? [],
            'operators' => $boat->operators ?? [],
            'durations' => $boat->durations ?? config('listing.durations'),
            'fuel' => $boat->fuel ?? 'renter',
            'insurance_owner' => $boat->insurance_owner ?? [],
            'insurance_boat' => $boat->insurance_boat ?? [],
            'commercial' => $commercial ? [
                'carrier' => $commercial['carrier'],
                'policy' => $commercial['policy'],
                'expires' => $owner->now()->addDays($commercial['expires_days'])->toDateString(),
                'liability' => $commercial['liability'],
                'coi' => $commercial['coi'],
            ] : [],
            'pricing' => $pricing ? [
                'hourly' => $dollars($pricing['hourly_cents'] ?? null),
                'min_hours' => $pricing['min_hours'] ?? null,
                'weekend_markup_bp' => $pricing['weekend_markup_bp'] ?? config('listing.pricing.default_weekend_markup_bp'),
                'cleaning_fee' => $dollars($pricing['cleaning_fee_cents'] ?? null),
                'fixed' => array_map($dollars, $pricing['fixed'] ?? []),
            ] : [],
        ];
    }
}
