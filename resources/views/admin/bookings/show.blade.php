@extends('layouts.admin', ['title' => 'Booking '.$booking->reference, 'active' => 'bookings'])

{{--
    A booking (Admin → Bookings, 4.8): the trip, the guest's contact (admin only), every price line, the payments,
    the history, the admin's notes and the status changes allowed now (a reason + the password).
    Since 5.4 (D23: no money goes back without the admin): cancel a confirmed trip with the refund the admin chooses,
    refund without cancelling, approve / decline the refund a guest's online cancellation asked for, and the
    disputes Stripe reported. Assigning a captain comes with Phase 8.
--}}
@php
    $tz = config('app.display_timezone');
    $start = $booking->starts_at->setTimezone($tz);
    $end = $booking->ends_at->setTimezone($tz);
    $boat = $booking->boat;
    $facts = [
        ['Date', $start->format('l, F j, Y')],
        ['Time', $start->format('g:i A').' – '.$end->format('g:i A').' ('.$booking->hours.' '.Str::plural('hour', $booking->hours).')'],
        ['Boat', ($boat?->title ?? '—').($boat?->ownership === App\Enums\BoatOwnership::Owner ? ' (owner boat)' : ' (house boat)')],
        ['Experience', $booking->experience?->name ?? 'Private charter'],
        ['Guests', $booking->guests],
        ['Departure', $boat?->harbor?->name ?? '—'],
        ['Booked', $booking->created_at->setTimezone($tz)->format('M j, Y g:i A').' · '.ucfirst($booking->source)],
        ['Policy', $booking->policy_snapshot['name'] ?? '—'],
    ];
    $sensitiveErrors = $errors->getBag('sensitive');
    $dialogError = fn (string $dialog, string $field) => old('dialog') === $dialog ? $sensitiveErrors->first($field) : null;
    $reopen = fn (string $dialog) => old('dialog') === $dialog && $sensitiveErrors->any();
    $requested = $booking->refunds->where('status', App\Enums\RefundStatus::Requested);
    $radio = 'flex cursor-pointer items-start gap-3 rounded-xl p-3 ring-1 ring-navy-100 transition hover:ring-navy-300 has-checked:ring-2 has-checked:ring-navy-950';
@endphp

@section('content')
    <x-admin.page-header :title="$booking->reference" :description="($booking->experience?->name ?? 'Private charter').' · '.trim($booking->contact_first_name.' '.$booking->contact_last_name)" :back="['All bookings', route('admin.bookings.index')]">
        <x-slot:actions>
            <x-admin.pill :tone="$booking->status->tone()">{{ $booking->status->label() }}</x-admin.pill>
            @if ($targets !== [])
                <x-button variant="outline" data-open-modal="booking-status" icon-left="flag">Change status</x-button>
            @endif
            @if ($canRefund && $money['refundable'] > 0)
                <x-button variant="outline" data-open-modal="booking-refund" icon-left="hand-coins">Refund</x-button>
            @endif
            @if ($cancellable)
                <button type="button" data-open-modal="booking-cancel" class="inline-flex items-center justify-center gap-2 rounded-full border border-red-200 px-6 py-3 text-sm font-bold text-red-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white"><i data-lucide="circle-x" class="h-4 w-4"></i>Cancel booking</button>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="min-w-0 space-y-6">
            <x-admin.card title="Trip">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($facts as [$label, $value])
                        <div><dt class="text-xs font-bold uppercase tracking-[0.15em] text-navy-400">{{ $label }}</dt><dd class="mt-1 text-sm font-semibold text-navy-950">{{ $value }}</dd></div>
                    @endforeach
                </dl>
                @if ($booking->occasion || $booking->customer_notes)
                    <div class="mt-5 rounded-xl bg-brass-50 p-4 text-sm ring-1 ring-brass-200">
                        @if ($booking->occasion)<p class="font-bold text-navy-950">{{ $booking->occasion }}</p>@endif
                        @if ($booking->customer_notes)<p class="mt-1 whitespace-pre-line text-navy-700">"{{ $booking->customer_notes }}"</p>@endif
                    </div>
                @endif
                @if ($booking->status === App\Enums\BookingStatus::Hold)
                    <p class="mt-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">On hold, not paid yet. The hold ends {{ $booking->hold_expires_at?->setTimezone($tz)->format('M j, g:i A') }}.</p>
                @endif
                @if ($booking->status === App\Enums\BookingStatus::Cancelled)
                    <p class="mt-5 rounded-xl bg-red-50 p-4 text-sm text-red-900 ring-1 ring-red-200">Cancelled{{ $booking->cancelled_at ? ' on '.$booking->cancelled_at->setTimezone($tz)->format('M j, Y g:i A') : '' }}{{ $booking->cancelled_by_role ? ' by the '.($booking->cancelled_by_role === 'customer' ? 'guest' : $booking->cancelled_by_role) : '' }}.{{ $booking->cancellation_reason ? ' '.$booking->cancellation_reason : '' }}</p>
                @endif
                @if ($requested->isNotEmpty())
                    <p class="mt-5 flex items-start gap-2 rounded-xl bg-amber-50 p-4 text-sm font-semibold text-amber-900 ring-1 ring-amber-200"><i data-lucide="hand-coins" class="mt-0.5 h-4 w-4 shrink-0"></i>The guest's refund waits for your approval — see Refunds. No money goes back until you approve it.</p>
                @endif
            </x-admin.card>

            <x-admin.card title="Captain">
                @if ($booking->captain)
                    <p class="font-bold text-navy-950">{{ $booking->captain->name }}</p>
                    <p class="text-xs text-navy-500">{{ $booking->captain->email }} · {{ $booking->captain->phone }}</p>
                @else
                    <p class="text-sm text-navy-500">No captain yet. Assigning captains and their trip offers come with the captain portal (Phase 8).</p>
                @endif
            </x-admin.card>

            {{-- Notes: the internal note and the note for the captain (shown to the captain from Phase 8). --}}
            <x-admin.card title="Notes">
                <form method="POST" action="{{ route('admin.bookings.notes', $booking->reference) }}" class="space-y-5">
                    @csrf
                    @method('PUT')
                    <x-form.textarea name="admin_notes" label="Internal note" :value="$booking->admin_notes" rows="3" maxlength="2000" hint="Only the admin team sees this." />
                    <x-form.textarea name="captain_notes" label="Note for the captain" :value="$booking->captain_notes" rows="3" maxlength="1000" hint="Shown to the captain with the trip. Never put the guest's name, phone or email here." placeholder="e.g. Birthday group, cake on board." />
                    <x-button type="submit" variant="dark">Save notes</x-button>
                </form>
            </x-admin.card>

            <x-admin.card title="History">
                <ol class="space-y-4 border-l border-navy-100 pl-5">
                    @foreach ($booking->statusHistory as $entry)
                        <li class="relative">
                            <span @class(['absolute -left-[25px] top-1 h-2.5 w-2.5 rounded-full', 'bg-brass-400' => $loop->first, 'bg-emerald-500' => ! $loop->first && $entry->to_status === App\Enums\BookingStatus::Confirmed, 'bg-navy-300' => ! $loop->first && $entry->to_status !== App\Enums\BookingStatus::Confirmed])></span>
                            <p class="text-sm font-semibold text-navy-900">{{ $entry->from_status ? $entry->from_status->label().' → ' : '' }}{{ $entry->to_status->label() }}</p>
                            <p class="text-xs text-navy-400">{{ $entry->created_at->setTimezone($tz)->format('M j, Y g:i A') }} · {{ $entry->actor?->name ?? 'System' }}{{ $entry->note ? ' · '.$entry->note : '' }}</p>
                        </li>
                    @endforeach
                </ol>
            </x-admin.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-admin.card title="Guest">
                <p class="font-bold text-navy-950">{{ trim($booking->contact_first_name.' '.$booking->contact_last_name) ?: '—' }}</p>
                <dl class="mt-3 space-y-1.5 text-sm">
                    @if ($booking->contact_email)<div class="flex items-center gap-2"><i data-lucide="mail" class="h-4 w-4 text-navy-400"></i><a href="mailto:{{ $booking->contact_email }}" class="font-semibold text-navy-900 hover:underline">{{ $booking->contact_email }}</a></div>@endif
                    @if ($booking->contact_phone)<div class="flex items-center gap-2"><i data-lucide="phone" class="h-4 w-4 text-navy-400"></i><a href="tel:{{ preg_replace('/[^0-9+]/', '', $booking->contact_phone) }}" class="font-semibold text-navy-900 hover:underline">{{ $booking->contact_phone }}</a></div>@endif
                    @if ($booking->heard_from)<div class="text-xs text-navy-400">Heard about us: {{ $booking->heard_from }}</div>@endif
                </dl>
                @if ($booking->customer)
                    <a href="{{ route('admin.users.show', $booking->customer) }}" class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-brass-600 hover:text-brass-700">Their account <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                @endif
                <p class="mt-3 text-xs text-navy-400">Contact details are visible to admins only.</p>
            </x-admin.card>

            <x-admin.card title="Price">
                <dl class="space-y-2.5 text-sm">
                    @foreach ($booking->items as $item)
                        <div class="flex justify-between gap-4"><dt class="text-navy-500">{{ $item->label }}<span class="block text-[11px] text-navy-400">{{ $item->description }} · {{ $item->payee->value }}</span></dt><dd @class(['font-semibold', 'text-emerald-700' => $item->amount_cents < 0, 'text-navy-900' => $item->amount_cents >= 0])>{{ $item->amount_cents < 0 ? '−'.money(-$item->amount_cents, 'always') : money($item->amount_cents, 'always') }}</dd></div>
                    @endforeach
                    <div class="flex justify-between gap-4 border-t border-navy-100 pt-3"><dt class="font-bold text-navy-950">Total</dt><dd class="font-bold text-navy-950">{{ money($booking->total_cents, 'always') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-navy-500">Paid</dt><dd class="font-semibold text-emerald-700">{{ money($booking->amount_paid_cents, 'always') }}</dd></div>
                    @if ($booking->amount_refunded_cents > 0)
                        <div class="flex justify-between gap-4"><dt class="text-navy-500">Refunded</dt><dd class="font-semibold text-red-600">−{{ money($booking->amount_refunded_cents, 'always') }}</dd></div>
                    @endif
                    @if ($booking->promoCode)
                        <div class="flex justify-between gap-4"><dt class="text-navy-500">Promo code</dt><dd class="font-mono font-semibold text-navy-900">{{ $booking->promoCode->code }}</dd></div>
                    @endif
                </dl>
            </x-admin.card>

            <x-admin.card title="Payments">
                @forelse ($booking->payments as $payment)
                    <div @class(['flex items-start justify-between gap-3 text-sm', 'mt-3 border-t border-navy-100 pt-3' => ! $loop->first])>
                        <div>
                            <p class="font-semibold text-navy-900">{{ money($payment->amount_cents, 'always') }}{{ $payment->card_last4 ? ' · '.ucfirst((string) $payment->card_brand).' •••• '.$payment->card_last4 : '' }}</p>
                            <p class="text-xs text-navy-400">{{ ucfirst($payment->provider) }} · {{ ($payment->paid_at ?? $payment->created_at)->setTimezone($tz)->format('M j, g:i A') }}{{ $payment->failure_message ? ' · '.$payment->failure_message : '' }}</p>
                        </div>
                        <x-admin.pill :tone="match ($payment->status) { App\Enums\PaymentIntentStatus::Succeeded => 'success', App\Enums\PaymentIntentStatus::Failed, App\Enums\PaymentIntentStatus::Canceled => 'muted', default => 'warning' }">{{ str_replace('_', ' ', ucfirst($payment->status->value)) }}</x-admin.pill>
                    </div>
                @empty
                    <p class="text-sm text-navy-500">No payment yet.</p>
                @endforelse
                @if ($booking->admin_notes && str_contains($booking->admin_notes, 'refund the guest') && $money['refundable'] > 0)
                    <p class="mt-4 rounded-xl bg-red-50 p-3 text-xs font-semibold text-red-700 ring-1 ring-red-200">This guest paid after the time was taken: refund them with "Refund" above.</p>
                @endif
                @if ($money['card_fee'] > 0)
                    <p class="mt-4 text-xs text-navy-400">Card processing fee: {{ money($money['card_fee'], 'always') }}{{ $booking->payments->contains(fn ($p) => $p->fee_cents === null && $p->status === App\Enums\PaymentIntentStatus::Succeeded) ? ' (estimated)' : '' }}. Stripe keeps it on a refund; it is kept back from the guest's refund once (D23).</p>
                @endif
            </x-admin.card>

            <x-admin.card title="Refunds">
                @forelse ($booking->refunds as $refund)
                    <div @class(['text-sm', 'mt-4 border-t border-navy-100 pt-4' => ! $loop->first])>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-bold text-navy-950">{{ money($refund->amount_cents, 'always') }}</p>
                                <p class="text-xs text-navy-500">{{ $refund->reason->label() }}{{ $refund->policy_pct !== null ? ' · policy '.$refund->policy_pct.'%' : '' }}{{ $refund->fee_cents > 0 ? ' · card fee '.money($refund->fee_cents, 'always').' kept' : '' }}</p>
                                <p class="text-xs text-navy-400">{{ $refund->created_at->setTimezone($tz)->format('M j, g:i A') }}{{ $refund->decider ? ' · '.$refund->decider->name : '' }}</p>
                            </div>
                            <x-admin.pill :tone="$refund->status->tone()">{{ $refund->status->label() }}</x-admin.pill>
                        </div>
                        @if ($refund->note)
                            <p class="mt-2 whitespace-pre-line rounded-lg bg-navy-50 p-2.5 text-xs text-navy-700">{{ $refund->reason === App\Enums\RefundReason::CustomerCancel && $refund->requested_by === $booking->customer_id ? 'Guest: ' : '' }}{{ $refund->note }}</p>
                        @endif
                        @if ($refund->decision_note)
                            <p class="mt-2 text-xs text-navy-500">Decision: {{ $refund->decision_note }}</p>
                        @endif
                        @if ($refund->failure_reason)
                            <p class="mt-2 text-xs font-semibold text-red-600">{{ $refund->failure_reason }}</p>
                        @endif
                        @if ($canRefund && $refund->status === App\Enums\RefundStatus::Requested)
                            <div class="mt-3 flex flex-wrap gap-2">
                                <x-button variant="dark" size="sm" data-open-modal="approve-{{ $refund->ulid }}">Approve</x-button>
                                <x-button variant="outline" size="sm" data-open-modal="decline-{{ $refund->ulid }}">Decline</x-button>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-navy-500">No refunds.</p>
                @endforelse
                <p class="mt-4 text-xs text-navy-400">Left to refund: {{ money($money['refundable'], 'always') }}.</p>
            </x-admin.card>

            @if ($booking->disputes->isNotEmpty())
                <x-admin.card title="Disputes">
                    @foreach ($booking->disputes as $dispute)
                        <div @class(['flex items-start justify-between gap-3 text-sm', 'mt-3 border-t border-navy-100 pt-3' => ! $loop->first])>
                            <div>
                                <p class="font-semibold text-navy-900">{{ money($dispute->amount_cents, 'always') }} · {{ str_replace('_', ' ', (string) $dispute->reason) }}</p>
                                <p class="text-xs text-navy-400">{{ $dispute->created_at->setTimezone($tz)->format('M j, Y') }}{{ $dispute->evidence_due_by && $dispute->isOpen() ? ' · evidence due '.$dispute->evidence_due_by->setTimezone($tz)->format('M j, g:i A') : '' }}</p>
                            </div>
                            <x-admin.pill :tone="$dispute->isOpen() ? 'danger' : 'muted'">{{ $dispute->statusLabel() }}</x-admin.pill>
                        </div>
                    @endforeach
                    <p class="mt-4 text-xs text-navy-400">Answer a dispute in the Stripe dashboard.</p>
                </x-admin.card>
            @endif
        </div>
    </div>

    @push('modals')
        @if ($targets !== [])
            <x-admin.modal id="booking-status" title="Change {{ $booking->reference }}'s status" :open="$reopen('booking-status')">
                <x-admin.sensitive-form id="booking-status" :action="route('admin.bookings.status', $booking->reference)" submit="Change status">
                    <x-form.select name="status" id="booking-status-select" label="New status" :options="$targets" required :error="$dialogError('booking-status', 'status')" />
                </x-admin.sensitive-form>
            </x-admin.modal>
        @endif

        @if ($cancellable)
            @php($preview = $money['preview'])
            <x-admin.modal id="booking-cancel" title="Cancel {{ $booking->reference }}" wide :open="$reopen('booking-cancel')">
                <x-admin.sensitive-form id="booking-cancel" :action="route('admin.bookings.cancel', $booking->reference)" submit="Cancel booking" danger>
                    <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                    <p class="text-sm text-navy-600">The time becomes free and the guest is emailed. The refund is sent to their card at once.</p>
                    <x-form.select name="cause" id="booking-cancel-cause" label="Why" :options="$cancelCauses" required :error="$dialogError('booking-cancel', 'cause')" />
                    <fieldset>
                        <legend class="text-sm font-bold text-navy-800">Refund<span class="text-red-600" aria-hidden="true"> *</span></legend>
                        <div class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ([
                                'policy' => ['By the policy', $preview['percent'].'% · '.money($preview['policy_cents'], 'always')],
                                'full' => ['Everything paid', money($money['refundable'], 'always')],
                                'custom' => ['Custom amount', 'Type it below'],
                                'none' => ['No refund', '$0'],
                            ] as $mode => [$title, $detail])
                                <label class="{{ $radio }}">
                                    <input type="radio" name="refund" value="{{ $mode }}" class="mt-0.5 h-4 w-4 shrink-0 accent-navy-950" @checked(old('refund', 'policy') === $mode)>
                                    <span><span class="block text-sm font-bold text-navy-950">{{ $title }}</span><span class="block text-xs text-navy-500">{{ $detail }}</span></span>
                                </label>
                            @endforeach
                        </div>
                        @if ($dialogError('booking-cancel', 'refund'))
                            <p class="mt-2 text-xs font-semibold text-red-600" data-field-error>{{ $dialogError('booking-cancel', 'refund') }}</p>
                        @endif
                    </fieldset>
                    <x-form.input name="amount" id="booking-cancel-amount" label="Custom amount (USD)" inputmode="decimal" placeholder="e.g. 250" maxlength="10" hint="Sent exactly as typed. At most {{ money($money['refundable'], 'always') }}." :error="$dialogError('booking-cancel', 'amount')" />
                    <x-form.checkbox name="keep_fee" id="booking-cancel-fee" hidden-zero :checked="true">Keep back the card processing fee ({{ money($money['fee'], 'always') }}) from "By the policy" and "Everything paid" <span class="block text-xs font-normal text-navy-500">Stripe doesn't return it on a refund; the guest bears it (D23).</span></x-form.checkbox>
                </x-admin.sensitive-form>
            </x-admin.modal>
        @endif

        @if ($canRefund && $money['refundable'] > 0)
            <x-admin.modal id="booking-refund" title="Refund {{ $booking->reference }}" :open="$reopen('booking-refund')">
                <x-admin.sensitive-form id="booking-refund" :action="route('admin.bookings.refunds.store', $booking->reference)" submit="Send refund">
                    <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                    <p class="text-sm text-navy-600">Money back to the guest's card without cancelling the booking. It is sent at once.</p>
                    <x-form.select name="cause" id="booking-refund-cause" label="Why" :options="$refundCauses" required :error="$dialogError('booking-refund', 'cause')" />
                    <x-form.input name="amount" id="booking-refund-amount" label="Amount (USD)" inputmode="decimal" required maxlength="10" placeholder="e.g. 150" hint="At most {{ money($money['refundable'], 'always') }} (what is left of the payment)." :error="$dialogError('booking-refund', 'amount')" />
                </x-admin.sensitive-form>
            </x-admin.modal>
        @endif

        @if ($canRefund)
            @foreach ($requested as $refund)
                <x-admin.modal id="approve-{{ $refund->ulid }}" title="Approve the refund" :open="$reopen('approve-'.$refund->ulid)">
                    <x-admin.sensitive-form :id="'approve-'.$refund->ulid" :action="route('admin.bookings.refunds.approve', [$booking->reference, $refund])" submit="Approve and send">
                        <p class="text-sm text-navy-600">By the {{ $booking->policy_snapshot['name'] ?? '' }} policy ({{ $refund->policy_pct }}%), less the {{ money($refund->fee_cents, 'always') }} card fee, the guest gets <strong class="text-navy-950">{{ money($refund->amount_cents, 'always') }}</strong>. Change the amount if needed.</p>
                        <x-form.input name="amount" :id="'approve-amount-'.$refund->ulid" label="Amount (USD)" inputmode="decimal" required maxlength="10" :value="App\Support\Money::input($refund->amount_cents)" hint="At most {{ money($money['max'][$refund->ulid] ?? 0, 'always') }}." :error="$dialogError('approve-'.$refund->ulid, 'amount')" />
                    </x-admin.sensitive-form>
                </x-admin.modal>
                <x-admin.modal id="decline-{{ $refund->ulid }}" title="Decline the refund" :open="$reopen('decline-'.$refund->ulid)">
                    <x-admin.sensitive-form :id="'decline-'.$refund->ulid" :action="route('admin.bookings.refunds.decline', [$booking->reference, $refund])" submit="Decline refund" danger>
                        <p class="text-sm text-navy-600">The booking stays cancelled and nothing is refunded. Your reason below is emailed to the guest.</p>
                    </x-admin.sensitive-form>
                </x-admin.modal>
            @endforeach
        @endif
    @endpush
@endsection
