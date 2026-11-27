<?php

namespace App\Http\Requests\Admin;

use App\Support\Money;

/**
 * The admin approves a guest's refund (PLAN 5.4, D23): the amount to send — the policy's by default, changed when
 * needed (RefundService checks it against what is left) — a reason and the password.
 */
class ApproveRefundRequest extends RefundDecisionRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100000'],
        ];
    }

    public function amountCents(): int
    {
        return (int) Money::toCents($this->validated('amount'));
    }
}
