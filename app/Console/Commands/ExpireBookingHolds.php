<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\PaymentIntentStatus;
use App\Models\Booking;
use App\Services\BookingService;
use App\Services\Payments\PaymentGateway;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Holds that ran out become `expired` (PLAN §7.3), and their open payment intents are cancelled so the card form
 * can't be paid any more. Availability already ignores a hold past its time; this keeps the records and the
 * provider tidy. Runs every minute from the scheduler (routes/console.php). A payment that still comes in later
 * is handled by ConfirmPaidBooking (confirmed if the time is still free, else marked for a refund).
 */
class ExpireBookingHolds extends Command
{
    protected $signature = 'bookings:expire-holds';

    protected $description = 'Mark holds that ran out as expired and cancel their open payments';

    public function handle(BookingService $bookings, PaymentGateway $gateway): int
    {
        $count = 0;
        Booking::query()->where('status', BookingStatus::Hold)->where('hold_expires_at', '<=', now())->orderBy('id')
            ->each(function (Booking $booking) use ($bookings, $gateway, &$count) {
                $expired = DB::transaction(function () use ($booking, $bookings) {
                    $booking = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->first();
                    if ($booking === null || $booking->status !== BookingStatus::Hold || $booking->hold_expires_at?->isFuture()) {
                        return null; // paid or changed meanwhile
                    }

                    return $bookings->transition($booking, BookingStatus::Expired, null, 'The hold ran out');
                });
                if ($expired === null) {
                    return;
                }
                $count++;
                foreach ($expired->payments()->get() as $payment) {
                    if ($payment->status->isOpen() && $payment->status !== PaymentIntentStatus::Processing) {
                        $gateway->cancel($payment);
                        $payment->forceFill(['status' => PaymentIntentStatus::Canceled])->save();
                    }
                }
            });

        $this->info("Expired {$count} ".($count === 1 ? 'hold' : 'holds').'.');

        return self::SUCCESS;
    }
}
