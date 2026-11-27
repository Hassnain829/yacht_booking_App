<?php

namespace App\Http\Requests\Admin;

use App\Enums\RefundReason;
use App\Support\Money;
use Illuminate\Validation\Rule;

/**
 * The admin cancels a confirmed trip (PLAN 5.4): why (the guest asked by phone, weather, the owner, …), which refund
 * — the policy's, the full amount, a custom amount or none — and whether the card fee is kept back (D23), with a
 * reason and the password (SensitiveActionRequest). The amounts for "policy" and "full" are worked out on the
 * server; only a custom amount is typed, and RefundService checks it against what is left to refund.
 */
class CancelBookingRequest extends SensitiveActionRequest
{
    public const MODES = ['policy', 'full', 'custom', 'none'];

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
            'cause' => ['required', 'string', Rule::in(array_keys(RefundReason::cancelCauses()))],
            'refund' => ['required', 'string', Rule::in(self::MODES)],
            'amount' => ['nullable', 'required_if:refund,custom', 'numeric', 'decimal:0,2', 'min:0.01', 'max:100000'],
            'keep_fee' => ['nullable', 'boolean'],
            'idempotency_key' => ['required', 'string', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return parent::messages() + ['amount.required_if' => 'Type the amount to refund.'];
    }

    public function cause(): RefundReason
    {
        return RefundReason::from((string) $this->validated('cause'));
    }

    public function mode(): string
    {
        return (string) $this->validated('refund');
    }

    public function customCents(): ?int
    {
        return $this->mode() === 'custom' ? Money::toCents($this->validated('amount')) : null;
    }
}
