@extends('layouts.admin', ['title' => $listing->display_title, 'active' => 'listings'])

@php
    $p = $listing->pricing ?? [];
    $s = $listing->specs ?? [];
    $checks = [
        [count($listing->photos ?? []) >= config('listing.rules_engine.min_photos'), 'At least '.config('listing.rules_engine.min_photos').' photos', ($listing->photos_count).' added'],
        [! empty($p['hourly_cents']), 'Pricing set', ! empty($p['hourly_cents']) ? money($p['hourly_cents'], 'never').'/hr' : 'Missing'],
        [(bool) ($listing->insurance_type), 'Insurance details', $listing->insurance_type === 'commercial' ? ($listing->commercial['carrier'] ?? 'Carrier missing') : 'Recreational + partner policy'],
        [! empty($s['passengers']), 'Passenger capacity', ($s['passengers'] ?? '—').' guests'],
        [(int) ($s['length'] ?? 0) <= config('listing.rules_engine.renter_max_length_ft') || ! ($listing->operators['renter'] ?? false), 'Over 32 ft → captained only', ($s['length'] ?? '—').' ft'],
    ];
@endphp

@section('content')
    <x-admin.page-header :title="$listing->display_title" :description="$listing->owner.' · '.$listing->status_label" :back="['Listing reviews', route('admin.listings.index')]">
        @if ($listing->status === 'pending')
            <x-slot:actions>
                <x-button variant="outline" data-open-modal="changes-modal">Request changes</x-button>
                <button type="button" data-open-modal="reject-modal" class="inline-flex items-center justify-center gap-2 rounded-full border border-red-200 px-6 py-3 text-sm font-bold text-red-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white">Reject</button>
                <x-button variant="primary" data-open-modal="approve-modal" icon-left="check">Approve</x-button>
            </x-slot:actions>
        @endif
    </x-admin.page-header>

    <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            <x-admin.card title="Photos">
                @if ($listing->photos)
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($listing->photos as $i => $photo)
                            <img src="{{ asset($photo) }}" alt="Photo {{ $i + 1 }}" loading="lazy" class="aspect-[4/3] w-full rounded-xl object-cover">
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-navy-500">No photos yet.</p>
                @endif
            </x-admin.card>
            <x-admin.card title="Listing text">
                <p class="font-display text-xl text-navy-950">{{ $listing->title ?: '—' }}</p>
                <p class="mt-3 whitespace-pre-line text-sm leading-relaxed text-navy-600">{{ $listing->description ?: '—' }}</p>
            </x-admin.card>
            <x-admin.card title="Specifications & location">
                <dl class="grid gap-x-6 gap-y-3 text-sm sm:grid-cols-2">
                    @foreach (['Year' => $s['year'] ?? null, 'Make & model' => trim(($s['make'] ?? '').' '.($s['model'] ?? '')), 'Length' => isset($s['length']) ? $s['length'].' ft' : null, 'Category' => $s['category'] ?? null, 'Passengers' => $s['passengers'] ?? null, 'Registration / HIN' => $s['registration'] ?? null, 'Location' => ($listing->location['type'] ?? '').' · '.($listing->location['street'] ?? '').', '.($listing->location['city'] ?? ''), 'Cancellation policy' => $policy['name'] ?? null] as $label => $value)
                        <div class="flex justify-between gap-4 border-b border-navy-100 pb-2"><dt class="text-navy-500">{{ $label }}</dt><dd class="text-right font-semibold text-navy-950">{{ $value ?: '—' }}</dd></div>
                    @endforeach
                </dl>
            </x-admin.card>
        </div>
        <div class="min-w-0 space-y-6">
            <x-admin.card title="Review checklist">
                <ul class="space-y-3">
                    @foreach ($checks as [$ok, $label, $detail])
                        <li class="flex items-start gap-3"><span @class(['mt-0.5 grid h-5 w-5 shrink-0 place-items-center rounded-full', 'bg-emerald-600 text-white' => $ok, 'bg-red-100 text-red-600' => ! $ok])><i data-lucide="{{ $ok ? 'check' : 'x' }}" class="h-3 w-3"></i></span><span class="text-sm"><span class="font-semibold text-navy-900">{{ $label }}</span><span class="block text-xs text-navy-500">{{ $detail }}</span></span></li>
                    @endforeach
                </ul>
            </x-admin.card>
            <x-admin.card title="Owner's pricing">
                @if ($p)
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-navy-500">Hourly</dt><dd class="font-bold text-navy-950">{{ money($p['hourly_cents'], 'never') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-navy-500">Weekend markup</dt><dd class="font-semibold text-navy-950">+{{ \App\Support\Money::percent($p['weekend_markup_bp']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-navy-500">Minimum</dt><dd class="font-semibold text-navy-950">{{ $p['min_hours'] }} hours</dd></div>
                        <div class="flex justify-between"><dt class="text-navy-500">Fuel & cleaning</dt><dd class="font-semibold text-navy-950">{{ money($p['cleaning_fee_cents'], 'never') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-navy-500">Commission</dt><dd class="font-semibold text-navy-950">{{ \App\Support\Money::percent($commissionBp) }}</dd></div>
                    </dl>
                @else
                    <p class="text-sm text-navy-500">Not set yet.</p>
                @endif
            </x-admin.card>
        </div>
    </div>

    @push('modals')
        <x-admin.modal id="approve-modal" title="Approve and publish">
            <form method="POST" action="#" class="space-y-4" data-demo-form="Demo: approvals go live in Phase 6.">
                @csrf
                <x-form.input name="hourly" id="approve-hourly" label="Confirm hourly rate (USD)" :value="isset($p['hourly_cents']) ? $p['hourly_cents'] / 100 : ''" required inputmode="decimal" maxlength="9" />
                <x-form.input name="commission" id="approve-commission" label="Commission for this owner (%)" :value="$commissionBp / 100" required inputmode="decimal" maxlength="5" hint="Leave the default unless you agreed something else with the owner." />
                <div class="flex justify-end gap-2"><x-button type="button" variant="outline" data-close-modal>Cancel</x-button><x-button type="submit" variant="dark">Approve and publish</x-button></div>
            </form>
        </x-admin.modal>
        <x-admin.modal id="changes-modal" title="Request changes">
            <x-admin.sensitive-form id="changes" submit="Send to owner" message="Demo: the owner gets an email with your notes (Phase 6)." />
        </x-admin.modal>
        <x-admin.modal id="reject-modal" title="Reject listing">
            <x-admin.sensitive-form id="reject" submit="Reject listing" danger message="Demo: rejections go live in Phase 6." />
        </x-admin.modal>
    @endpush
@endsection
