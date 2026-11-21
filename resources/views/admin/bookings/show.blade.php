@extends('layouts.admin', ['title' => 'Booking '.$booking->ref, 'active' => 'bookings', 'page' => 'admin-booking'])

@php
    $facts = [
        ['Date', $booking->starts_at->format('l, F j, Y')],
        ['Time', $booking->starts_at->format('g:i A').' – '.$booking->ends_at->format('g:i A')],
        ['Boat', $booking->boat.($booking->kind === 'owner' ? ' (owner: '.$booking->owner.')' : ' (house boat)')],
        ['Experience', $booking->experience],
        ['Guests', $booking->guests],
        ['Departure', $booking->harbor],
    ];
@endphp

@section('content')
    <x-admin.page-header :title="$booking->ref" :description="$booking->experience.' · '.$booking->customer" :back="['All bookings', route('admin.bookings.index')]">
        <x-slot:actions>
            <x-admin.pill :tone="$booking->status_tone">{{ $booking->status_label }}</x-admin.pill>
            @if ($booking->status !== 'cancelled')
                <x-button variant="outline" data-open-modal="refund-modal" icon-left="undo-2">Refund</x-button>
            @endif
            @if ($booking->is_upcoming)
                <button type="button" data-open-modal="cancel-modal" class="inline-flex items-center justify-center gap-2 rounded-full border border-red-200 px-6 py-3 text-sm font-bold text-red-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white">Cancel trip</button>
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="min-w-0 space-y-6">
            <x-admin.card title="Trip">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($facts as [$label, $value])
                        <div><dt class="text-xs font-bold uppercase tracking-[0.15em] text-navy-400">{{ $label }}</dt><dd class="mt-1 text-sm font-semibold text-navy-950">{{ $value }}</dd></div>
                    @endforeach
                </dl>
            </x-admin.card>

            {{-- Captain assignment (PLAN §7.8): checks + email with NO customer details --}}
            <x-admin.card title="Captain">
                @if ($booking->status === 'cancelled')
                    <p class="text-sm text-navy-500">This trip was cancelled.</p>
                @else
                    @if ($current)
                        <div class="mb-5 flex items-center gap-4 rounded-xl bg-navy-50/70 p-4">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-navy-950 text-sm font-bold text-brass-300">{{ $current->initials }}</span>
                            <div class="min-w-0 flex-1"><p class="font-bold text-navy-950">{{ $current->name }}</p><p class="text-xs text-navy-500">{{ $current->email }} · {{ $current->phone }}</p></div>
                            <x-admin.pill tone="success">Email sent</x-admin.pill>
                        </div>
                    @endif
                    <form method="POST" action="#" class="space-y-5" data-demo-form="Demo: captain emails go live in Phase 8." id="assign-form">
                        @csrf
                        <fieldset>
                            <legend class="text-sm font-bold text-navy-800">{{ $current ? 'Reassign to' : 'Assign a captain' }}</legend>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                @foreach ($captains as $captain)
                                    <label @class(['relative flex cursor-pointer gap-3 rounded-xl p-4 ring-1 transition has-[:checked]:ring-2 has-[:checked]:ring-navy-950', 'ring-navy-100 hover:ring-navy-300' => $captain->eligible, 'cursor-not-allowed bg-navy-50/60 ring-navy-100' => ! $captain->eligible])>
                                        <input type="radio" name="captain" value="{{ $captain->id }}" class="mt-1 h-4 w-4 accent-navy-950" @checked($current?->id === $captain->id) @disabled(! $captain->eligible) required>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-bold text-navy-950">{{ $captain->name }}</span>
                                            <span class="block text-xs text-navy-500">{{ $captain->email }} · {{ $captain->phone }}</span>
                                            <span class="block text-xs text-navy-400">{{ $captain->license_label }} · {{ money($captain->rate_cents, 'never') }}/hr</span>
                                            <ul class="mt-2 space-y-1">
                                                @foreach ($captain->checks as [$label, $ok])
                                                    <li @class(['flex items-center gap-1.5 text-[11px] font-semibold', 'text-emerald-700' => $ok, 'text-red-600' => ! $ok])><i data-lucide="{{ $ok ? 'check' : 'x' }}" class="h-3.5 w-3.5"></i>{{ $label }}</li>
                                                @endforeach
                                            </ul>
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </fieldset>
                        <x-form.textarea name="captain_notes" label="Notes for captain" rows="3" maxlength="1000" hint="Sent with the assignment email. The email never includes the guest's name, phone or email — add anything the captain needs here." placeholder="e.g. Birthday group, cake on board. Please call me at the dock if anything changes." />
                        <div class="flex flex-wrap items-center gap-3">
                            <x-button type="submit" variant="dark" icon-left="send">{{ $current ? 'Reassign and email' : 'Assign and email captain' }}</x-button>
                            @if ($current)
                                <button type="button" data-soon class="text-sm font-bold text-brass-600 hover:text-brass-700">Resend email to {{ $current->name }}</button>
                            @endif
                        </div>
                    </form>
                @endif
            </x-admin.card>

            <x-admin.card title="History">
                <ol class="space-y-4 border-l border-navy-100 pl-5">
                    <li class="relative"><span class="absolute -left-[25px] top-1 h-2.5 w-2.5 rounded-full bg-brass-400"></span><p class="text-sm font-semibold text-navy-900">Booked online</p><p class="text-xs text-navy-400">{{ $booking->customer }} · deposit paid</p></li>
                    @if ($booking->captain)
                        <li class="relative"><span class="absolute -left-[25px] top-1 h-2.5 w-2.5 rounded-full bg-navy-300"></span><p class="text-sm font-semibold text-navy-900">Captain assigned · {{ $booking->captain }}</p><p class="text-xs text-navy-400">Sam Rivera</p></li>
                    @endif
                    @if ($booking->status === 'completed')
                        <li class="relative"><span class="absolute -left-[25px] top-1 h-2.5 w-2.5 rounded-full bg-emerald-500"></span><p class="text-sm font-semibold text-navy-900">Trip completed</p><p class="text-xs text-navy-400">{{ $booking->ends_at->format('M j, g:i A') }}</p></li>
                    @elseif ($booking->status === 'cancelled')
                        <li class="relative"><span class="absolute -left-[25px] top-1 h-2.5 w-2.5 rounded-full bg-red-500"></span><p class="text-sm font-semibold text-navy-900">Cancelled by the guest · refunded</p></li>
                    @endif
                </ol>
            </x-admin.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-admin.card title="Guest">
                <p class="font-bold text-navy-950">{{ $booking->customer }}</p>
                <p class="mt-1 text-sm text-navy-500">Contact details are visible to admins only.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-button variant="outline" size="sm" data-soon icon-left="mail">Email</x-button>
                    <x-button variant="outline" size="sm" data-soon icon-left="phone">Call</x-button>
                </div>
            </x-admin.card>

            <x-admin.card title="Payment">
                <dl class="space-y-2.5 text-sm">
                    <div class="flex justify-between gap-4"><dt class="text-navy-500">Total</dt><dd class="font-bold text-navy-950">{{ money($booking->total_cents, 'always') }}</dd></div>
                    <div class="flex justify-between gap-4"><dt class="text-navy-500">Paid</dt><dd class="font-semibold text-emerald-700">{{ money($booking->paid_cents, 'always') }}</dd></div>
                    @if ($booking->balance_cents > 0)
                        <div class="flex justify-between gap-4"><dt class="text-navy-500">Balance (auto-charged 7 days before)</dt><dd class="font-semibold text-navy-950">{{ money($booking->balance_cents, 'always') }}</dd></div>
                    @endif
                </dl>
            </x-admin.card>
        </div>
    </div>

    @push('modals')
        <x-admin.modal id="refund-modal" title="Refund {{ $booking->ref }}">
            <x-admin.sensitive-form id="refund" submit="Issue refund" danger message="Demo: refunds go live with Stripe (Phase 5).">
                <x-form.input name="amount" id="refund-amount" label="Amount (USD)" inputmode="decimal" required pattern="\d{1,6}(\.\d{1,2})?" maxlength="9" :hint="'Up to '.money($booking->paid_cents, 'always').' (paid so far).'" />
            </x-admin.sensitive-form>
        </x-admin.modal>
        <x-admin.modal id="cancel-modal" title="Cancel {{ $booking->ref }}">
            <x-admin.sensitive-form id="cancel" submit="Cancel trip" danger message="Demo: admin cancellations go live in Phase 4.">
                <x-form.select name="cancel_type" id="cancel-type" label="Type" required :options="['weather' => 'Weather or safety — full refund or new date', 'owner' => 'Owner cancelled — full refund (deduction possible)', 'customer' => 'Customer asked — policy refund', 'admin' => 'Other — custom refund']" placeholder="Choose…" />
            </x-admin.sensitive-form>
        </x-admin.modal>
    @endpush
@endsection
