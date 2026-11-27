<?php

namespace App\Http\Requests\Admin;

use App\Enums\RefundReason;
use App\Support\Money;
use Illuminate\Validation\Rule;

/**
 * The admin refunds a booking without cancelling it (PLAN §7.4 admin override, 5.4): a complaint, a payment that came
 * in after the time was taken, weather cutting a trip short, goodwill. The amount in dollars (RefundService checks
 * it against what is left), why, a reason and the password.
 */
class IssueRefundRequest extends SensitiveActionRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('issue-refunds');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'cause' => ['required', 'string', Rule::in(array_keys(RefundReason::refundCauses()))],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100000'],
            'idempotency_key' => ['required', 'string', 'uuid'],
        ];
    }

    public function cause(): RefundReason
    {
        return RefundReason::from((string) $this->validated('cause'));
    }

    public function amountCents(): int
    {
        return (int) Money::toCents($this->validated('amount'));
    }
}
