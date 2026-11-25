@extends('layouts.admin', ['title' => 'Settings', 'active' => 'settings'])

@use('App\Support\Money')

@php
    $field = 'w-full rounded-xl border bg-white py-3 pl-4 pr-16 text-sm text-navy-950 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
    // [form field, label, value shown, unit, min, max, step, hint]
    $groups = [
        'Booking & pricing' => [
            ['tax_rate', 'Sales tax', Money::percentInput($settings['tax_rate_bp']), '%', 0, 25, '0.01', 'Added to every booking.'],
            ['weekend_markup', 'Weekend rate (Fri–Sun)', Money::percentInput($settings['weekend_multiplier_bp'] - 10000), '%', 0, 100, '0.01', 'Added to hourly rates unless a boat sets its own.'],
            ['hold_minutes', 'Checkout hold', $settings['hold_minutes'], 'min', 5, 60, '1', 'How long a slot is held while the guest pays.'],
        ],
        'Owners & payouts' => [
            ['commission', 'Commission', Money::percentInput($settings['commission_bp']), '%', 0, 50, '0.01', 'Of the charter price on owner boats. Can be changed per owner.'],
            ['payout_delay_hours', 'Earnings available after', $settings['payout_delay_hours'], 'hours', 0, 720, '1', 'Counted from the end of the trip.'],
            ['complaint_window_hours', 'Complaint window', $settings['complaint_window_hours'], 'hours', 0, 720, '1', 'How long guests can report a problem after a trip.'],
            ['min_withdrawal', 'Minimum withdrawal', Money::input($settings['min_withdrawal_cents']), 'USD', 0, 10000, '0.01', null],
        ],
        'Captains (Phase 8)' => [
            ['captain_offer_hours', 'Trip offers stay open', $settings['captain_offer_hours'], 'hours', 1, 168, '1', 'Default deadline when you ask captains to take a trip.'],
            ['captain_release_hours', 'Captains can give a trip back until', $settings['captain_release_hours'], 'hours', 0, 336, '1', 'Before the start. Closer than that, only you can remove them.'],
            ['captain_ack_reminder_hours', 'Remind to acknowledge after', $settings['captain_ack_reminder_hours'], 'hours', 1, 72, '1', 'For trips you assign directly.'],
            ['captain_turnaround_minutes', 'Gap between a captain\'s trips', $settings['captain_turnaround_minutes'], 'min', 0, 240, '1', null],
        ],
        'Trip extensions (Phase 8)' => [
            ['extension_cutoff_minutes', 'Extensions close', $settings['extension_cutoff_minutes'], 'min', 0, 120, '1', 'Before the trip ends.'],
            ['extension_hold_minutes', 'Extension hold', $settings['extension_hold_minutes'], 'min', 5, 30, '1', 'How long the extra time is held while the guest pays.'],
            ['extension_max_trip_hours', 'Longest trip', $settings['extension_max_trip_hours'], 'hours', 2, 24, '1', 'A trip can\'t be extended past this length.'],
        ],
    ];
    $options = old('gratuity_options') !== null ? array_map('intval', (array) old('gratuity_options')) : $settings['gratuity_options'];
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

    <form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 space-y-6">
        @csrf
        @method('PUT')
        @foreach ($groups as $group => $fields)
            <x-admin.card :title="$group">
                <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($fields as [$name, $label, $value, $unit, $min, $max, $step, $hint])
                        <div>
                            <label for="set-{{ $name }}" class="text-sm font-bold text-navy-800">{{ $label }}</label>
                            <div class="relative mt-2">
                                <input id="set-{{ $name }}" name="{{ $name }}" type="number" step="{{ $step }}" min="{{ $min }}" max="{{ $max }}" required value="{{ old($name, $value) }}"
                                       @if ($errors->has($name)) aria-invalid="true" aria-describedby="set-{{ $name }}-error" @endif
                                       @class([$field, 'border-red-500' => $errors->has($name), 'border-navy-200' => ! $errors->has($name)])>
                                <span class="pointer-events-none absolute right-4 top-1/2 -translate-y-1/2 text-xs font-bold text-navy-400">{{ $unit }}</span>
                            </div>
                            @if ($errors->has($name))<p data-field-error id="set-{{ $name }}-error" class="mt-1.5 text-xs font-semibold text-red-600">{{ $errors->first($name) }}</p>@elseif ($hint)<p class="mt-1.5 text-xs text-navy-500">{{ $hint }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </x-admin.card>
        @endforeach

        <x-admin.card title="Gratuity">
            <fieldset>
                <legend class="text-sm font-bold text-navy-800">Tip options at checkout</legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    @foreach ($gratuityChoices as $pct)
                        <label class="flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold ring-1 ring-navy-200 has-[:checked]:bg-navy-950 has-[:checked]:text-white has-[:checked]:ring-navy-950">
                            <input type="checkbox" name="gratuity_options[]" value="{{ $pct }}" @checked(in_array($pct, $options, true)) class="h-4 w-4 accent-brass-400">{{ $pct }}%
                        </label>
                    @endforeach
                </div>
                @if ($errors->first('gratuity_options') ?: $errors->first('gratuity_options.*'))<p data-field-error class="mt-1.5 text-xs font-semibold text-red-600">{{ $errors->first('gratuity_options') ?: $errors->first('gratuity_options.*') }}</p>@endif
            </fieldset>
            <x-form.select name="default_gratuity" label="Chosen by default" :options="collect($gratuityChoices)->mapWithKeys(fn ($p) => [$p => $p.'%'])->all()" :value="$settings['default_gratuity']" required class="mt-5 max-w-xs" />
        </x-admin.card>

        <x-admin.card title="Business details">
            <div class="grid gap-5 md:grid-cols-2">
                <x-form.input name="business_name" label="Business name" :value="$settings['business_name']" required maxlength="80" />
                <x-form.input name="business_email" type="email" label="Email (used for contact form replies)" :value="$settings['business_email']" required maxlength="254" />
                <x-form.input name="business_phone" label="Phone" :value="$settings['business_phone']" required maxlength="30" />
                <x-form.input name="business_hours" label="Opening hours" :value="$settings['business_hours']" maxlength="80" />
                <x-form.input name="business_office" label="Office address" :value="$settings['business_office']" maxlength="160" />
                <x-form.input name="business_tagline" label="Tagline" :value="$settings['business_tagline']" maxlength="255" />
            </div>
        </x-admin.card>

        <x-admin.card title="Confirm">
            <x-admin.confirm-fields placeholder="Why are you changing these settings?" />
        </x-admin.card>

        <div class="flex justify-end"><x-button type="submit" variant="dark" size="lg">Save settings</x-button></div>
    </form>
@endsection
