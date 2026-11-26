<?php

namespace App\Actions\Booking;

use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Enums\PaymentStatus;
use App\Models\Addon;
use App\Models\Boat;
use App\Models\Booking;
use App\Models\Experience;
use App\Models\User;
use App\Services\AvailabilityService;
use App\Services\BookingService;
use App\Services\Payments\PaymentGateway;
use App\Services\QuoteService;
use App\Services\SettingsService;
use App\Support\Pricing\QuoteRequest;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Book now" (PLAN 4.5, §7.3): the trip is held for the guest for `hold_minutes` (Settings) while they pay.
 *
 * Inside one transaction that locks the boat's row — the **double-booking lock**: two guests asking for the same
 * time are handled one after the other, and the second one's AvailabilityService check sees the first hold.
 * The same `hold_key` (the browser's idempotency key) returns the same booking, so a double click makes one hold.
 * A guest keeps one hold at a time: a new one lets go of their older open holds (they went back and chose again).
 * The price is QuoteService's, with the default gratuity, until the guest changes their choices at checkout.
 */
class HoldTrip
{
    public function __construct(
        private AvailabilityService $availability,
        private QuoteService $quotes,
        private BookingService $bookings,
        private SettingsService $settings,
        private PaymentGateway $gateway,
    ) {}

    /**
     * @param  list<Addon>  $addons
     *
     * @throws ValidationException when the trip isn't free or can't be priced
     */
    public function handle(User $user, Boat $boat, ?Experience $experience, CarbonImmutable $startsAt, int $hours, int $guests, array $addons, string $holdKey): Booking
    {
        $released = [];

        $booking = DB::transaction(function () use ($user, $boat, $experience, $startsAt, $hours, $guests, $addons, $holdKey, &$released) {
            $same = Booking::query()->where('hold_key', $holdKey)->where('customer_id', $user->getKey())->first();
            if ($same !== null) {
                return $same;
            }

            $locked = Boat::query()->whereKey($boat->getKey())->lockForUpdate()->firstOrFail();
            $locked->setRelations($boat->getRelations()); // availability rules, experiences, policy, already loaded

            foreach (Booking::query()->where('customer_id', $user->getKey())->where('status', BookingStatus::Hold)->lockForUpdate()->get() as $old) {
                if ($old->takesTime()) {
                    $this->bookings->transition($old, BookingStatus::Expired, $user, 'Let go: the guest held another trip', ['hold_expires_at' => now()]);
                    $released[] = $old;
                }
            }

            $this->availability->ensureFree($locked, $startsAt, $hours, $experience, $guests);
            $settings = $this->settings->all();
            $quote = $this->quotes->quote(new QuoteRequest($locked, $experience, $startsAt, $hours, $guests, $addons, gratuityPct: (int) $settings['default_gratuity']));
            $policy = $locked->cancellationPolicy;

            $booking = (new Booking)->forceFill([
                'reference' => Booking::newReference(),
                'customer_id' => $user->getKey(),
                'boat_id' => $locked->getKey(),
                'experience_id' => $experience?->getKey(),
                'starts_at' => $startsAt->utc(),
                'ends_at' => $startsAt->utc()->addHours($hours),
                'hours' => $hours,
                'guests' => $guests,
                'contact_first_name' => $user->first_name,
                'contact_last_name' => $user->last_name,
                'contact_email' => $user->email,
                'contact_phone' => $user->phone,
                'payment_status' => PaymentStatus::Unpaid,
                'policy_snapshot' => $policy ? ['slug' => $policy->slug, 'name' => $policy->name, 'rules' => $policy->rules, 'points' => $policy->points] : null,
                'hold_expires_at' => now()->addMinutes(max(1, (int) $settings['hold_minutes'])),
                'hold_key' => $holdKey,
                'source' => 'web',
            ]);
            $this->bookings->transition($booking, BookingStatus::Hold, $user, 'Held at checkout');
            $this->bookings->applyQuote($booking, $quote);

            return $booking;
        });

        // An older hold may have had a payment form open: its intent can't be paid any more.
        foreach ($released as $old) {
            foreach ($old->payments()->get() as $payment) {
                if ($payment->status->isOpen()) {
                    $this->gateway->cancel($payment);
                    $payment->forceFill(['status' => PaymentIntentStatus::Canceled])->save();
                }
            }
        }

        return $booking;
    }
}
