@extends('layouts.admin', ['title' => 'Complaints', 'active' => 'complaints'])

@section('content')
    <x-admin.page-header title="Complaints" description="Guest reports about a trip (sent within {{ $money['complaint_window_hours'] }} hours). While a report is open, that trip's owner earnings are on hold." />

    <div class="mt-6 space-y-4">
        @foreach ($complaints as $c)
            <article class="rounded-2xl bg-white p-5 shadow-xs ring-1 ring-navy-100 md:p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <x-admin.pill :tone="$c->is_open ? 'warning' : ($c->status === 'resolved' ? 'success' : 'muted')">{{ $c->is_open ? 'Open' : ucfirst($c->status) }}</x-admin.pill>
                    <span class="text-sm font-bold text-navy-950">{{ $c->category_label }}</span>
                    <span class="text-xs text-navy-400">· <a href="{{ route('admin.bookings.show', $c->booking->ref) }}" class="font-mono hover:underline">{{ $c->booking->ref }}</a> · {{ $c->booking->boat->short_title }} · reported {{ $c->opened_at->format('M j') }}</span>
                </div>
                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    <div class="rounded-xl bg-navy-50/70 p-4"><p class="text-xs font-bold uppercase tracking-[0.15em] text-navy-400">Guest · {{ $c->booking->customer }}@if ($c->photos) · {{ $c->photos }} photos @endif</p><p class="mt-2 text-sm leading-relaxed text-navy-800">{{ $c->description }}</p></div>
                    <div class="rounded-xl p-4 ring-1 ring-navy-100"><p class="text-xs font-bold uppercase tracking-[0.15em] text-navy-400">Owner's response</p><p class="mt-2 text-sm leading-relaxed text-navy-700">{{ $c->owner_response ?? 'No response yet.' }}</p></div>
                </div>
                @if ($c->is_open)
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <p class="text-xs font-semibold text-amber-800">{{ money($c->booking->net_cents, 'always') }} of owner earnings on hold.</p>
                        <x-button variant="dark" data-open-modal="resolve-{{ $c->ulid }}">Resolve</x-button>
                    </div>
                @else
                    <p class="mt-4 text-sm text-navy-600"><span class="font-bold text-navy-950">{{ $c->deduction_cents ? money($c->deduction_cents, 'always').' deducted' : 'No deduction' }}</span> · {{ $c->resolution_note }}</p>
                @endif
            </article>
        @endforeach
    </div>

    @push('modals')
        @foreach ($complaints->where('is_open', true) as $c)
            <x-admin.modal id="resolve-{{ $c->ulid }}" title="Resolve complaint · {{ $c->booking->ref }}">
                <x-admin.sensitive-form :id="'resolve-'.$c->ulid" submit="Resolve" message="Demo: resolutions update the owner ledger in Phase 7.">
                    <x-form.select name="resolution" :id="'resolution-'.$c->ulid" label="Outcome" required :options="['no_action' => 'No action — release the earnings', 'deduction' => 'Deduct from the owner', 'refund' => 'Refund the guest', 'deduction_and_refund' => 'Deduct and refund', 'reject' => 'Reject the complaint']" placeholder="Choose an outcome" />
                    <x-form.input name="deduction" :id="'deduction-'.$c->ulid" label="Deduction / refund amount (USD)" inputmode="decimal" pattern="\d{1,6}(\.\d{1,2})?" maxlength="9" :hint="'Up to '.money($c->booking->net_cents, 'always').' (this trip\'s owner earnings).'" />
                </x-admin.sensitive-form>
            </x-admin.modal>
        @endforeach
    @endpush
@endsection
