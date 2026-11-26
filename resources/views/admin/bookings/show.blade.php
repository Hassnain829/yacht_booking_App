@extends('layouts.admin', ['title' => 'Booking '.$booking->reference, 'active' => 'bookings'])

{{--
    A booking (Admin → Bookings, 4.8): the trip, the guest's contact (admin only), every price line, the payments,
    the history, the admin's notes and the status changes allowed now (a reason + the password). Cancelling with a
    refund (Phase 5) and assigning a captain (Phase 8) aren't here yet.
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
@endphp

@section('content')
    <x-admin.page-header :title="$booking->reference" :description="($booking->experience?->name ?? 'Private charter').' · '.trim($booking->contact_first_name.' '.$booking->contact_last_name)" :back="['All bookings', route('admin.bookings.index')]">
        <x-slot:actions>
            <x-admin.pill :tone="$booking->status->tone()">{{ $booking->status->label() }}</x-admin.pill>
            @if ($targets !== [])
                <x-button variant="outline" data-open-modal="booking-status" icon-left="flag">Change status</x-button>
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
                @if ($booking->admin_notes && str_contains($booking->admin_notes, 'refund the guest'))
                    <p class="mt-4 rounded-xl bg-red-50 p-3 text-xs font-semibold text-red-700 ring-1 ring-red-200">This guest paid after the time was taken: refund them (refunds come with Phase 5; until then from the Stripe dashboard).</p>
                @endif
                <p class="mt-4 text-xs text-navy-400">Cancelling with a refund comes with Phase 5.</p>
            </x-admin.card>
        </div>
    </div>

    @if ($targets !== [])
        @push('modals')
            <x-admin.modal id="booking-status" title="Change {{ $booking->reference }}'s status" :open="old('dialog') === 'booking-status' && $sensitiveErrors->any()">
                <x-admin.sensitive-form id="booking-status" :action="route('admin.bookings.status', $booking->reference)" submit="Change status">
                    <x-form.select name="status" id="booking-status-select" label="New status" :options="$targets" required :error="old('dialog') === 'booking-status' ? $sensitiveErrors->first('status') : null" />
                </x-admin.sensitive-form>
            </x-admin.modal>
        @endpush
    @endif
@endsection
