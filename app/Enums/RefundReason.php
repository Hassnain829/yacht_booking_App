<?php

namespace App\Enums;

/** Why money goes back to the guest (refunds.reason, PLAN §6.6 / §7.4). */
enum RefundReason: string
{
    case CustomerCancel = 'customer_cancel';
    case OwnerCancel = 'owner_cancel';
    case Weather = 'weather';
    case Complaint = 'complaint';
    case LatePayment = 'late_payment';
    case Admin = 'admin';
    case Dispute = 'dispute';

    public function label(): string
    {
        return match ($this) {
            self::CustomerCancel => 'The guest cancelled',
            self::OwnerCancel => 'The owner / boat cancelled',
            self::Weather => 'Weather or safety',
            self::Complaint => 'A complaint',
            self::LatePayment => 'Paid after the time was taken',
            self::Admin => 'Admin refund',
            self::Dispute => 'Dispute',
        };
    }

    /**
     * Why the admin cancels a trip (the cancel dialog).
     *
     * @return array<string, string>
     */
    public static function cancelCauses(): array
    {
        return [
            self::CustomerCancel->value => 'The guest asked (phone / email)',
            self::Weather->value => 'Weather or safety',
            self::OwnerCancel->value => 'The owner / boat can\'t run it',
            self::Admin->value => 'Something else',
        ];
    }

    /**
     * Why the admin refunds without cancelling (the refund dialog).
     *
     * @return array<string, string>
     */
    public static function refundCauses(): array
    {
        return [
            self::Complaint->value => 'A complaint about the trip',
            self::LatePayment->value => 'Paid after the time was taken',
            self::Weather->value => 'Weather cut the trip short',
            self::Admin->value => 'Something else (goodwill, a mistake…)',
        ];
    }
}
