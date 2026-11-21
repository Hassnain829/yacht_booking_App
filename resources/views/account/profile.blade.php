@extends('layouts.app', [
    'title' => 'Profile & Security',
    'page' => 'account-profile',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@php
    $card = 'rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-8';
@endphp

@section('content')
    <x-account.shell active="profile" heading="Profile & security" intro="Member since {{ $profile->member_since->format('F Y') }}. Keep your details up to date — we use them for booking confirmations and trip reminders.">
        <div class="mt-10 grid items-start gap-6 lg:grid-cols-[13rem_minmax(0,1fr)]">
            <nav class="hidden lg:sticky lg:top-28 lg:block" aria-label="On this page">
                <ul class="space-y-1 text-sm font-semibold">
                    @foreach (['details' => 'Your details', 'password' => 'Password', 'two-factor' => 'Two-factor login', 'sessions' => 'Devices', 'cards' => 'Payment methods', 'delete' => 'Delete account'] as $anchor => $label)
                        <li><a href="#{{ $anchor }}" class="block rounded-xl px-4 py-2.5 text-navy-500 transition hover:bg-white hover:text-navy-950">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <div class="space-y-6">
                {{-- Details (Phase 2: PUT /user/profile-information via Fortify) --}}
                <section id="details" class="{{ $card }} scroll-mt-28" aria-labelledby="details-title">
                    <h2 id="details-title" class="text-lg font-extrabold text-navy-950">Your details</h2>
                    <form method="POST" action="{{ url('/user/profile-information') }}" class="mt-6 grid gap-5 sm:grid-cols-2" data-demo-form="Demo: your details are saved once accounts go live (Phase 2).">
                        @csrf
                        @method('PUT')
                        <x-form.input name="first_name" label="First name" :value="$profile->first_name" required maxlength="60" autocomplete="given-name" />
                        <x-form.input name="last_name" label="Last name" :value="$profile->last_name" required maxlength="60" autocomplete="family-name" />
                        <x-form.input name="email" type="email" label="Email" :value="$profile->email" required maxlength="254" autocomplete="email" hint="If you change it, we'll send a link to confirm the new address." />
                        <x-form.input name="phone" type="tel" label="Mobile phone" :value="$profile->phone" maxlength="30" autocomplete="tel" hint="Your captain texts this number before your trip." />
                        <x-form.checkbox name="marketing" :checked="$profile->marketing" class="sm:col-span-2">Send me seasonal offers and fireworks-night reminders (no more than twice a month).</x-form.checkbox>
                        <div class="sm:col-span-2"><x-button type="submit" variant="dark">Save details</x-button></div>
                    </form>
                </section>

                {{-- Password (Phase 2: PUT /user/password) --}}
                <section id="password" class="{{ $card }} scroll-mt-28" aria-labelledby="password-title">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="password-title" class="text-lg font-extrabold text-navy-950">Password</h2>
                        <p class="text-xs text-navy-400">Last changed {{ $profile->password_changed_at->format('M j, Y') }}</p>
                    </div>
                    <form method="POST" action="{{ url('/user/password') }}" class="mt-6 grid gap-5 sm:grid-cols-2" data-demo-form="Demo: password changes go live in Phase 2.">
                        @csrf
                        @method('PUT')
                        <x-form.password name="current_password" label="Current password" autocomplete="current-password" class="sm:col-span-2" />
                        <x-form.password name="password" label="New password" id="f-new-password" autocomplete="new-password" minlength="12" maxlength="128" hint="At least 12 characters, with upper- and lower-case letters and a number." />
                        <x-form.password name="password_confirmation" label="Confirm new password" autocomplete="new-password" minlength="12" maxlength="128" data-match="f-new-password" />
                        <div class="sm:col-span-2"><x-button type="submit" variant="dark">Update password</x-button></div>
                    </form>
                </section>

                {{-- Two-factor (Phase 2: Fortify two-factor authentication) --}}
                <section id="two-factor" class="{{ $card }} scroll-mt-28" aria-labelledby="tfa-title">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="tfa-title" class="text-lg font-extrabold text-navy-950">Two-factor login</h2>
                        <span @class(['rounded-full px-2.5 py-1 text-[11px] font-bold', 'bg-emerald-100 text-emerald-800' => $profile->two_factor, 'bg-navy-100 text-navy-600' => ! $profile->two_factor])>{{ $profile->two_factor ? 'On' : 'Off' }}</span>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-navy-500">Add a second step when you log in: a 6-digit code from an authenticator app such as Google Authenticator or 1Password. Boat owners need it before their first withdrawal.</p>
                    <x-button variant="outline" class="mt-5" data-soon icon-left="shield-check">{{ $profile->two_factor ? 'Manage two-factor login' : 'Turn on two-factor login' }}</x-button>
                </section>

                {{-- Sessions (Phase 2: database sessions + "log out other devices" with password) --}}
                <section id="sessions" class="{{ $card }} scroll-mt-28" aria-labelledby="sessions-title">
                    <h2 id="sessions-title" class="text-lg font-extrabold text-navy-950">Devices</h2>
                    <p class="mt-2 text-sm text-navy-500">Where you're logged in. Don't recognise one? Log out of other devices and change your password.</p>
                    <ul class="mt-5 divide-y divide-navy-100">
                        @foreach ($sessions as $session)
                            <li class="flex items-center gap-4 py-4">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-navy-50 text-navy-500"><i data-lucide="{{ $session->icon }}" class="h-5 w-5"></i></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-navy-950">{{ $session->device }}</p>
                                    <p class="text-xs text-navy-500">{{ $session->location }} · {{ $session->current ? 'This device' : 'Last active '.$session->last_active->diffForHumans() }}</p>
                                </div>
                                @if ($session->current)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-800">Current</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <x-button variant="outline" class="mt-2" data-soon>Log out other devices</x-button>
                </section>

                {{-- Saved cards (Phase 5: Stripe payment methods; we only store brand + last 4) --}}
                <section id="cards" class="{{ $card }} scroll-mt-28" aria-labelledby="cards-title">
                    <h2 id="cards-title" class="text-lg font-extrabold text-navy-950">Payment methods</h2>
                    <p class="mt-2 text-sm text-navy-500">Cards are stored securely by Stripe — we only keep the brand and last 4 digits.</p>
                    <ul class="mt-5 space-y-3" id="card-list">
                        @foreach ($cards as $saved)
                            <li class="flex items-center gap-4 rounded-2xl p-4 ring-1 ring-navy-100" data-card>
                                <span class="grid h-10 w-14 shrink-0 place-items-center rounded-lg bg-navy-950 text-[10px] font-extrabold uppercase tracking-wider text-white">{{ ['Mastercard' => 'MC', 'American Express' => 'AMEX', 'Amex' => 'AMEX', 'Discover' => 'DISC'][$saved->brand] ?? $saved->brand }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-navy-950">{{ $saved->brand }} •••• {{ $saved->last4 }}</p>
                                    <p class="text-xs text-navy-500">Expires {{ $saved->expires }}</p>
                                </div>
                                @if ($saved->default)
                                    <span class="rounded-full bg-brass-100 px-2.5 py-1 text-[11px] font-bold text-brass-800">Default</span>
                                @else
                                    <button type="button" data-remove-card class="rounded-full px-3 py-2 text-xs font-bold text-navy-400 hover:bg-red-50 hover:text-red-600">Remove</button>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <x-button variant="outline" class="mt-5" data-soon icon-left="credit-card">Add a card</x-button>
                </section>

                {{-- Delete account (Phase 2: confirm password, keep booking records the law requires) --}}
                <section id="delete" class="{{ $card }} scroll-mt-28" aria-labelledby="delete-title">
                    <h2 id="delete-title" class="text-lg font-extrabold text-navy-950">Delete account</h2>
                    <p class="mt-2 text-sm leading-relaxed text-navy-500">We'll delete your profile and saved cards. Records of past bookings and payments are kept as long as the law requires. Upcoming trips must be cancelled first.</p>
                    <button type="button" data-soon class="mt-5 inline-flex items-center justify-center gap-2 rounded-full border border-red-200 px-6 py-3 text-sm font-bold text-red-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white">Delete my account</button>
                </section>
            </div>
        </div>
    </x-account.shell>
@endsection
