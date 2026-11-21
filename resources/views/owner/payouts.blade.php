@extends('layouts.app', [
    'title' => 'Payouts',
    'page' => 'owner-payouts',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@php
    $account = $profile->payout_account;
    $paused = $profile->payouts_paused;
    $min = $money['min_withdrawal_cents'];
    $checks = [
        [(bool) $account, 'Payout account connected'],
        [(bool) $profile->two_factor, 'Two-factor authentication turned on'],
        [$balances['available'] >= $min, 'At least '.money($min, 'never').' available'],
        [! $paused, 'Payouts active'],
    ];
    $statusClass = [
        'requested' => 'bg-amber-100 text-amber-800', 'approved' => 'bg-navy-100 text-navy-700', 'processing' => 'bg-navy-100 text-navy-700',
        'paid' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-red-100 text-red-700', 'on_hold' => 'bg-amber-100 text-amber-800', 'failed' => 'bg-red-100 text-red-700',
    ];
@endphp

@section('content')
    <x-owner.shell active="payouts" heading="Payouts" intro="Withdraw your available earnings to your bank account. Every withdrawal is reviewed by our team.">
        @if ($paused)
            <div class="mt-10 flex gap-3 rounded-2xl bg-amber-50 p-5 text-sm text-amber-900 ring-1 ring-amber-200" role="status">
                <i data-lucide="circle-pause" class="h-5 w-5 shrink-0"></i>
                <p><strong class="font-bold">Payouts are paused by our team</strong> since {{ $paused->since->format('M j, Y') }}: {{ $paused->reason }}. Your earnings are safe — contact us to resolve it.</p>
            </div>
        @endif

        <div class="mt-10 grid items-start gap-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,1fr)]">
            <section class="rounded-3xl bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8" aria-labelledby="withdraw-title">
                <h2 id="withdraw-title" class="text-lg font-extrabold text-navy-950">Request a withdrawal</h2>
                <div class="mt-5 rounded-2xl bg-navy-950 p-5 text-white">
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-brass-300">Available to withdraw</p>
                    <p class="mt-2 text-4xl font-extrabold" id="available-amount">{{ money($balances['available'], 'always') }}</p>
                    <p class="mt-1 text-xs text-white/60">Pending: {{ money($balances['pending'], 'always') }} · On hold: {{ money($balances['on_hold'], 'always') }}</p>
                </div>

                <form id="withdraw-form" class="mt-6" novalidate
                      data-withdraw="{{ json_encode(['available' => $balances['available'], 'min' => $min, 'enabled' => $canWithdraw, 'method' => $account ? $account->bank.' •••• '.$account->last4 : null]) }}">
                    <label for="withdraw-amount" class="text-sm font-bold text-navy-800">Amount (USD)</label>
                    <div class="relative mt-2">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm font-bold text-navy-400">$</span>
                        <input id="withdraw-amount" type="text" inputmode="decimal" autocomplete="off" maxlength="12" placeholder="{{ number_format($balances['available'] / 100, 2, '.', '') }}" @disabled(! $canWithdraw)
                               class="w-full rounded-xl border border-navy-200 bg-white py-3.5 pl-8 pr-28 text-sm text-navy-950 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100 disabled:bg-navy-50" aria-describedby="withdraw-help">
                        <button type="button" data-withdraw-all @disabled(! $canWithdraw) class="absolute right-2 top-1/2 -translate-y-1/2 rounded-lg bg-navy-50 px-3 py-2 text-xs font-bold text-navy-700 hover:bg-navy-100 disabled:opacity-40">Withdraw all</button>
                    </div>
                    <p id="withdraw-help" class="mt-1.5 text-xs text-navy-400">Minimum {{ money($min, 'never') }}. Sent to {{ $account ? $account->bank.' •••• '.$account->last4 : 'your payout account' }}.</p>
                    <x-button type="submit" variant="primary" size="block" class="mt-5" :disabled="! $canWithdraw">Request withdrawal</x-button>
                    <p class="mt-3 flex items-start gap-2 text-xs leading-relaxed text-navy-500"><i data-lucide="lock" class="h-4 w-4 shrink-0 text-brass-500"></i>You'll confirm with your password and two-factor code. Our team usually approves withdrawals within 1 business day.</p>
                </form>
            </section>

            <div class="space-y-6">
                <section id="payout-method" class="scroll-mt-28 rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-7" aria-labelledby="method-title">
                    <h2 id="method-title" class="text-lg font-extrabold text-navy-950">Payout method</h2>
                    @if ($account)
                        <div class="mt-4 flex items-center gap-4 rounded-2xl bg-navy-50/70 p-4">
                            <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-navy-950 text-brass-300"><i data-lucide="landmark" class="h-5 w-5"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold text-navy-950">{{ $account->bank }} •••• {{ $account->last4 }}</p>
                                <p class="text-xs text-navy-500">Connected with Stripe · {{ $account->connected_at->format('M Y') }}</p>
                            </div>
                            <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-800">Active</span>
                        </div>
                        <button type="button" data-soon class="mt-4 text-sm font-bold text-brass-600 hover:text-brass-700">Manage on Stripe</button>
                    @else
                        <p class="mt-2 text-sm text-navy-500">Connect a bank account through Stripe to receive your earnings. It takes about 5 minutes.</p>
                        <x-button variant="dark" class="mt-4" data-soon icon="arrow-right">Connect payouts</x-button>
                    @endif
                </section>

                <section class="rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-7" aria-labelledby="checks-title">
                    <h2 id="checks-title" class="text-lg font-extrabold text-navy-950">Before you withdraw</h2>
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach ($checks as [$ok, $label])
                            <li class="flex items-center gap-3 {{ $ok ? 'text-navy-800' : 'text-navy-400' }}">
                                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full {{ $ok ? 'bg-emerald-600 text-white' : 'bg-navy-100 text-navy-400' }}"><i data-lucide="{{ $ok ? 'check' : 'x' }}" class="h-3.5 w-3.5"></i></span>
                                {{ $label }}
                            </li>
                        @endforeach
                    </ul>
                </section>
            </div>
        </div>

        <section id="history" class="mt-8 scroll-mt-28 rounded-3xl bg-white shadow-xs ring-1 ring-navy-100" aria-labelledby="history-title">
            <h2 id="history-title" class="border-b border-navy-100 px-6 py-5 text-lg font-extrabold text-navy-950 md:px-7">Payout history</h2>
            <ul class="divide-y divide-navy-100" id="payout-history">
                @forelse ($payouts as $payout)
                    <li class="grid gap-x-4 gap-y-1 px-6 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center md:px-7">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-navy-950">{{ money($payout->amount_cents, 'always') }} <span class="font-semibold text-navy-400">· {{ $payout->ref }}</span></p>
                            <p class="mt-0.5 text-xs text-navy-500">Requested {{ $payout->requested_at->format('M j, Y') }}@if ($payout->paid_at) · paid {{ $payout->paid_at->format('M j, Y') }}@endif · {{ $payout->method }} · {{ $payout->trips }} {{ Str::plural('trip', $payout->trips) }}</p>
                        </div>
                        <span class="justify-self-start rounded-full px-2.5 py-0.5 text-[11px] font-bold sm:justify-self-end {{ $statusClass[$payout->status] ?? 'bg-navy-100 text-navy-700' }}">{{ ucfirst(str_replace('_', ' ', $payout->status)) }}</span>
                    </li>
                @empty
                    <li class="px-6 py-10 text-center text-sm text-navy-500" data-empty>No payouts yet.</li>
                @endforelse
            </ul>
        </section>
    </x-owner.shell>
@endsection
