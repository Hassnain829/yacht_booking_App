<?php

namespace App\Enums;

/** How much of a booking is paid (PLAN §6.5 bookings.payment_status; Stripe in Phase 5). */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case DepositPaid = 'deposit_paid';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
    case Failed = 'failed';
}
