@extends('layouts.admin', ['title' => 'Settings', 'active' => 'settings'])

@php
    $field = 'w-full rounded-xl border border-navy-200 bg-white py-3 pl-4 pr-14 text-sm text-navy-950 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
    $groups = [
        'Booking & pricing' => [
            ['tax_rate', 'Sales tax', $pricing['tax_rate_bp'] / 100, '%', '0', '20', 'Added to every booking.'],
            ['deposit', 'Deposit', $pricing['deposit_bp'] / 100, '%', '10', '100', 'Charged at checkout when the trip is far enough away.'],
            ['balance_days', 'Balance charged', $pricing['balance_due_days'], 'days', '1', '30', 'Before the trip. Closer trips are paid in full.'],
            ['weekend', 'Weekend rate (Fri–Sun)', ($pricing['weekend_multiplier_bp'] - 10000) / 100, '%', '0', '100', 'Added to house boat hourly rates.'],
            ['hold_minutes', 'Checkout hold', 15, 'min', '5', '60', 'How long a slot is held while the guest pays.'],
        ],
        'Owners & payouts' => [
            ['commission', 'Commission', $money['commission_bp'] / 100, '%', '0', '50', 'Of the charter price on owner boats. Can be changed per owner.'],
            ['payout_delay', 'Earnings available after', $money['payout_delay_hours'], 'hours', '0', '720', 'Counted from the end of the trip.'],
            ['complaint_window', 'Complaint window', $money['complaint_window_hours'], 'hours', '0', '720', 'How long guests can report a problem after a trip.'],
            ['min_withdrawal', 'Minimum withdrawal', $money['min_withdrawal_cents'] / 100, 'USD', '0', '10000', null],
        ],
    ];
@endphp

@section('content')
    <x-admin.page-header title="Settings" description="Business rules used across the site. Changes apply to new bookings only — existing bookings keep the price and policy they were booked with." />

    {{-- Real since Phase 2.4: two-factor login is the admin's own choice (user decision 2026-11-22). --}}
    <x-admin.card title="Security" id="security" class="mt-6">
        <x-slot:actions>
            <x-admin.pill :tone="$twoFactorOn ? 'success' : 'warning'">Two-factor login {{ $twoFactorOn ? 'on' : 'off' }}</x-admin.pill>
        </x-slot:actions>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="max-w-2xl">
                <p class="text-sm font-bold text-navy-950">Two-factor login for your admin account</p>
                <p class="mt-1 text-sm leading-relaxed text-navy-500">Your choice: turn it on or off at any time. When it is on, every login also asks for a 6-digit code from an authenticator app. Recommended on the live site — this account can approve payouts, refund payments and see every customer's details.</p>
            </div>
            <x-button :href="route('account.two-factor')" variant="outline" icon-left="shield-check" class="shrink-0">{{ $twoFactorOn ? 'Manage or turn off' : 'Turn on two-factor login' }}</x-button>
        </div>
    </x-admin.card>

    <form method="POST" action="#" class="mt-6 space-y-6" data-demo-form="Demo: settings are saved once the database is connected (Phase 3). Changes need your password and are logged.">
        @csrf
        @method('PUT')
        @foreach ($groups as $group => $fields)
            <x-admin.card :title="$group">
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($fields as [$name, $label, $value, $unit, $min, $max, $hint])
                        <div>
                            <label for="set-{{ $name }}" class="text-sm font-bold text-navy-800">{{ $label }}</label>
                            <div class="relative mt-2">
                                <input id="set-{{ $name }}" name="{{ $name }}" type="number" step="any" min="{{ $min }}" max="{{ $max }}" required value="{{ $value }}" class="{{ $field }}">
                                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-navy-400">{{ $unit }}</span>
                            </div>
                            @if ($hint)<p class="mt-1.5 text-xs text-navy-500">{{ $hint }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
        @endforeach

        <x-admin.card title="Gratuity options">
            <div class="flex flex-wrap gap-3">
                @foreach ([0, 10, 15, 18, 20, 25] as $pct)
                    <label class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-navy-200 has-[:checked]:bg-navy-950 has-[:checked]:text-white has-[:checked]:ring-navy-950">
                        <input type="checkbox" name="gratuity_options[]" value="{{ $pct }}" @checked(in_array($pct, $pricing['gratuity_options'], true)) class="h-4 w-4 accent-brass-400">{{ $pct }}%
                    </label>
                @endforeach
            </div>
        </x-admin.card>

        <x-admin.card title="Business details">
            <div class="grid gap-5 md:grid-cols-2">
                <x-form.input name="business_name" label="Business name" :value="$business['name']" required maxlength="80" />
                <x-form.input name="business_email" type="email" label="Email (used for contact form replies)" :value="$business['email']" required maxlength="254" />
                <x-form.input name="business_phone" label="Phone" :value="$business['phone']" required maxlength="30" />
                <x-form.input name="business_hours" label="Opening hours" :value="$business['hours']" maxlength="80" />
            </div>
        </x-admin.card>

        <div class="flex justify-end"><x-button type="submit" variant="dark" size="lg">Save settings</x-button></div>
    </form>
@endsection
