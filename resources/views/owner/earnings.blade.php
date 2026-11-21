@extends('layouts.app', [
    'title' => 'Earnings',
    'page' => 'owner',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@php
    $hours = $money['payout_delay_hours'];
    $cards = [
        ['wallet', 'Available', $balances['available'], 'Ready to withdraw to your bank.', true],
        ['hourglass', 'Pending', $balances['pending'], $balances['next_release'] ? 'Next release '.$balances['next_release']->format('M j, g:i A') : 'Released '.$hours.' hours after each trip.', false],
        ['shield-alert', 'On hold', $balances['on_hold'], 'Trips with an open guest report.', false],
        ['badge-dollar-sign', 'Paid out', $balances['paid'], 'Total sent to your bank so far.', false],
    ];
@endphp

@section('content')
    <x-owner.shell active="earnings" heading="Earnings" intro="What each trip earned after commission, and when it becomes available to withdraw.">
        <x-slot:actions>
            <x-button :href="route('owner.payouts.index')" variant="dark" icon="arrow-right">Withdraw</x-button>
        </x-slot:actions>

        <div class="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($cards as [$icon, $label, $amount, $note, $highlight])
                <div @class(['rounded-3xl p-5 ring-1', 'bg-navy-950 text-white ring-navy-950 shadow-card' => $highlight, 'bg-white ring-navy-100 shadow-xs' => ! $highlight])>
                    <div class="flex items-center justify-between gap-3">
                        <p @class(['text-xs font-bold uppercase tracking-[0.18em]', 'text-brass-300' => $highlight, 'text-navy-400' => ! $highlight])>{{ $label }}</p>
                        <i data-lucide="{{ $icon }}" @class(['h-5 w-5', 'text-brass-300' => $highlight, 'text-navy-300' => ! $highlight])></i>
                    </div>
                    <p @class(['mt-3 text-3xl font-extrabold', 'text-navy-950' => ! $highlight])>{{ money($amount, 'always') }}</p>
                    <p @class(['mt-1 text-xs', 'text-white/60' => $highlight, 'text-navy-500' => ! $highlight])>{{ $note }}</p>
                </div>
            @endforeach
        </div>

        <section class="mt-8 rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-7" aria-labelledby="how-earnings">
            <h2 id="how-earnings" class="text-lg font-extrabold text-navy-950">How earnings work</h2>
            <ol class="mt-5 grid gap-4 md:grid-cols-4">
                @foreach ([
                    ['sailboat', 'Trip ends', 'The charter price minus our '.\App\Support\Money::percent($money['commission_bp']).' commission, plus your fuel & cleaning fee, is added as pending.'],
                    ['hourglass', $hours.'-hour window', 'Guests can report a problem for '.$hours.' hours after the trip. A report puts that trip on hold.'],
                    ['wallet', 'Available', 'After '.$hours.' hours with no open report, the amount becomes available to withdraw.'],
                    ['landmark', 'Withdraw', 'Request a withdrawal of '.money($money['min_withdrawal_cents'], 'never').' or more. Our team approves it and sends it to your bank.'],
                ] as $i => [$icon, $stepTitle, $text])
                    <li class="flex gap-3">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full {{ $i === 0 ? 'bg-brass-400 text-navy-950' : 'bg-navy-50 text-brass-700' }}"><i data-lucide="{{ $icon }}" class="h-4 w-4"></i></span>
                        <span><span class="block text-sm font-bold text-navy-950">{{ $stepTitle }}</span><span class="mt-0.5 block text-xs leading-relaxed text-navy-500">{{ $text }}</span></span>
                    </li>
                @endforeach
            </ol>
        </section>

        <section class="mt-8 rounded-3xl bg-white shadow-xs ring-1 ring-navy-100" aria-labelledby="statement">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-navy-100 px-6 py-5 md:px-7">
                <h2 id="statement" class="text-lg font-extrabold text-navy-950">Statement</h2>
                <button type="button" data-soon class="inline-flex items-center gap-1.5 text-sm font-bold text-brass-600 hover:text-brass-700"><i data-lucide="download" class="h-4 w-4"></i>Export CSV</button>
            </div>
            <ul class="divide-y divide-navy-100">
                @forelse ($ledger as $row)
                    <li class="grid gap-x-4 gap-y-1 px-6 py-4 sm:grid-cols-[6.5rem_minmax(0,1fr)_auto] sm:items-center md:px-7">
                        <p class="text-xs font-semibold text-navy-500">{{ $row->date->format('M j, Y') }}</p>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-bold text-navy-950">{{ $row->title }} <span class="font-semibold text-navy-400">· {{ $row->ref }}</span></p>
                            <p class="mt-0.5 truncate text-xs text-navy-500">{{ $row->detail }}</p>
                        </div>
                        <div class="flex items-center justify-between gap-3 sm:block sm:text-right">
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $row->status_class }}">{{ $row->status_label }}</span>
                            <p @class(['text-sm font-extrabold sm:mt-1', 'text-red-600' => $row->amount_cents < 0, 'text-navy-950' => $row->amount_cents >= 0])>{{ $row->amount_cents < 0 ? '−'.money(-$row->amount_cents, 'always') : money($row->amount_cents, 'always') }}</p>
                            @if ($row->available_at)
                                <p class="hidden text-[11px] text-navy-400 sm:block">Available {{ $row->available_at->format('M j') }}</p>
                            @endif
                        </div>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-navy-500">Your first trip's earnings will show up here.</li>
                @endforelse
            </ul>
        </section>
    </x-owner.shell>
@endsection
