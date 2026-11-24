<?php

namespace App\Enums;

/** Pay a deposit now and the balance before the trip, or everything now (PLAN §7.1; bookings.payment_plan). */
enum PaymentPlan: string
{
    case Deposit = 'deposit';
    case Full = 'full';
}
