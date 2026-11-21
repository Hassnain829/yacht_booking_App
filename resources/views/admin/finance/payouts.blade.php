@extends('layouts.admin', ['title' => 'Payouts', 'active' => 'payouts'])

@section('content')
    <x-admin.page-header title="Payouts" description="Owner withdrawal requests. Earnings become available {{ $money['payout_delay_hours'] }} hours after a trip; the minimum withdrawal is {{ money($money['min_withdrawal_cents'], 'never') }}." />

    <div class="mt-6 space-y-4">
        @foreach ($withdrawals as $w)
            <article class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-navy-100">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-center">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-admin.pill :tone="$w->status === 'requested' ? 'warning' : 'muted'">{{ $w->status === 'requested' ? 'Waiting for approval' : 'On hold' }}</x-admin.pill>
                            <span class="font-mono text-xs text-navy-400">{{ $w->ref }}</span>
                            <span class="text-xs text-navy-400">· requested {{ $w->requested_at->diffForHumans() }}</span>
                        </div>
                        <p class="mt-2 text-lg font-extrabold text-navy-950">{{ money($w->amount_cents, 'always') }} <span class="text-sm font-semibold text-navy-500">to {{ $w->owner }} · {{ $w->method }}</span></p>
                        <p class="mt-1 text-xs text-navy-500">{{ $w->boat }} · available balance {{ money($w->available_cents, 'always') }}@if ($w->open_complaints) · <span class="font-bold text-amber-700">{{ $w->open_complaints }} open complaint (that trip's earnings are already on hold)</span>@endif</p>
                        @if (! empty($w->note))
                            <p class="mt-2 rounded-lg bg-navy-50 px-3 py-2 text-xs text-navy-600">{{ $w->note }}</p>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-button variant="outline" size="sm" data-open-modal="hold-{{ $w->ref }}">Hold</x-button>
                        <button type="button" data-open-modal="reject-{{ $w->ref }}" class="inline-flex items-center justify-center rounded-full border border-red-200 px-5 py-2.5 text-sm font-bold text-red-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white">Reject</button>
                        <x-button size="sm" data-open-modal="approve-{{ $w->ref }}" icon-left="check">Approve</x-button>
                    </div>
                </div>
            </article>
        @endforeach
    </div>

    <x-admin.card title="Owner controls" class="mt-6">
        <p class="text-sm text-navy-600">Pause all payouts of an owner (for example while a dispute is open), or add a manual adjustment to their balance. Both need a written reason and are recorded in the audit log.</p>
        <div class="mt-4 flex flex-wrap gap-2">
            <x-button variant="outline" data-open-modal="pause-modal" icon-left="circle-pause">Pause an owner's payouts</x-button>
            <x-button variant="outline" data-open-modal="adjust-modal" icon-left="plus">Manual adjustment</x-button>
        </div>
    </x-admin.card>

    @push('modals')
        @foreach ($withdrawals as $w)
            <x-admin.modal id="approve-{{ $w->ref }}" title="Approve {{ money($w->amount_cents, 'always') }} to {{ $w->owner }}">
                <x-admin.sensitive-form :id="'approve-'.$w->ref" submit="Approve and send" message="Demo: approved withdrawals are sent with Stripe Connect (Phase 7)." />
            </x-admin.modal>
            <x-admin.modal id="hold-{{ $w->ref }}" title="Put {{ $w->ref }} on hold">
                <x-admin.sensitive-form :id="'hold-'.$w->ref" submit="Hold withdrawal" />
            </x-admin.modal>
            <x-admin.modal id="reject-{{ $w->ref }}" title="Reject {{ $w->ref }}">
                <x-admin.sensitive-form :id="'reject-'.$w->ref" submit="Reject withdrawal" danger />
            </x-admin.modal>
        @endforeach
        <x-admin.modal id="pause-modal" title="Pause an owner's payouts">
            <x-admin.sensitive-form id="pause" submit="Pause payouts" danger>
                <x-form.select name="owner" id="pause-owner" label="Owner" required :options="['marcus' => 'Marcus Reed', 'dana' => 'Dana Fox']" placeholder="Choose an owner" />
            </x-admin.sensitive-form>
        </x-admin.modal>
        <x-admin.modal id="adjust-modal" title="Manual adjustment">
            <x-admin.sensitive-form id="adjust" submit="Add adjustment">
                <x-form.select name="owner" id="adjust-owner" label="Owner" required :options="['marcus' => 'Marcus Reed', 'dana' => 'Dana Fox']" placeholder="Choose an owner" />
                <x-form.input name="amount" id="adjust-amount" label="Amount (USD, use − for a deduction)" required pattern="-?\d{1,6}(\.\d{1,2})?" maxlength="10" placeholder="-150.00" />
            </x-admin.sensitive-form>
        </x-admin.modal>
    @endpush
@endsection
