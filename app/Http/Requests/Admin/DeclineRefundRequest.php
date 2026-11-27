<?php

namespace App\Http\Requests\Admin;

/** The admin declines a guest's refund (PLAN 5.4, D23): the reason (emailed to the guest) and the password. */
class DeclineRefundRequest extends RefundDecisionRequest {}
