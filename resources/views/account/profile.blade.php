@extends('layouts.app', [
    'title' => 'Profile & Security',
    'page' => 'account-profile',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@php
    $card = 'rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-8';
    $ok = 'mt-4 rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200';
    $status = session('status');
    $detailsErrors = $errors->updateProfileInformation;
    $passwordErrors = $errors->updatePassword;
    // The "current password" box shows when the email is being changed (JS shows it as you type; this covers a failed save).
    $changingEmail = $detailsErrors->has('current_password') || (is_string(old('email')) && strtolower(trim(old('email'))) !== $user->email);
@endphp

@section('content')
    <x-account.shell active="profile" heading="Profile & security" intro="Member since {{ $user->created_at->setTimezone(config('app.display_timezone'))->format('F Y') }}. Keep your details up to date — we use them for booking confirmations and trip reminders.">
        <div class="mt-10 grid items-start gap-6 lg:grid-cols-[13rem_minmax(0,1fr)]">
            <nav class="hidden lg:sticky lg:top-28 lg:block" aria-label="On this page">
                <ul class="space-y-1 text-sm font-semibold">
                    @foreach (['details' => 'Your details', 'password' => 'Password', 'two-factor' => 'Two-factor login', 'sessions' => 'Devices', 'cards' => 'Payment methods', 'delete' => 'Delete account'] as $anchor => $label)
                        <li><a href="#{{ $anchor }}" class="block rounded-xl px-4 py-2.5 text-navy-500 transition hover:bg-white hover:text-navy-950">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </nav>

            <div class="space-y-6">
                {{-- Details (Phase 2.5: PUT /user/profile-information via Fortify; changing the email needs the current password) --}}
                <section id="details" class="{{ $card }} scroll-mt-28" aria-labelledby="details-title">
                    <h2 id="details-title" class="text-lg font-extrabold text-navy-950">Your details</h2>
                    @if ($status === 'profile-information-updated')
                        <p class="{{ $ok }}" role="status">Your details are saved.</p>
                    @endif
                    @unless ($user->hasVerifiedEmail())
                        <div class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm leading-relaxed text-amber-900 ring-1 ring-amber-200">
                            <p>Please confirm <strong>{{ $user->email }}</strong> with the link we emailed you. Until then, listing a boat and the owner area stay closed.</p>
                            @if ($status === 'verification-link-sent')
                                <p class="mt-1 font-semibold" role="status">A new link is on its way.</p>
                            @else
                                <form method="POST" action="{{ route('verification.send') }}" class="mt-1">
                                    @csrf
                                    <button type="submit" class="font-bold underline underline-offset-2 hover:text-amber-950">Send the link again</button>
                                </form>
                            @endif
                        </div>
                    @endunless
                    <form method="POST" action="{{ route('user-profile-information.update') }}" class="mt-6 grid gap-5 sm:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <x-form.input name="first_name" label="First name" :value="$user->first_name" :error="$detailsErrors->first('first_name')" required maxlength="60" autocomplete="given-name" />
                        <x-form.input name="last_name" label="Last name" :value="$user->last_name" :error="$detailsErrors->first('last_name')" required maxlength="60" autocomplete="family-name" />
                        <x-form.input name="email" type="email" label="Email" :value="$user->email" :error="$detailsErrors->first('email')" required maxlength="254" autocomplete="email" data-current-email="{{ $user->email }}" hint="If you change it, we'll send a link to confirm the new address." />
                        <x-form.input name="phone" type="tel" label="Mobile phone" :value="$user->phone" :error="$detailsErrors->first('phone')" maxlength="30" autocomplete="tel" hint="Your captain texts this number before your trip." />
                        <div @class(['sm:col-span-2', 'hidden' => ! $changingEmail]) data-email-password>
                            <x-form.password name="current_password" id="f-email-password" label="Current password" :required="$changingEmail" :disabled="! $changingEmail" :error="$detailsErrors->first('current_password')" autocomplete="current-password" maxlength="128" hint="Needed to change your email address." class="sm:max-w-sm" />
                        </div>
                        <x-form.checkbox name="marketing" hidden-zero :checked="$user->marketing_opt_in" :error="$detailsErrors->first('marketing')" class="sm:col-span-2">Send me seasonal offers and fireworks-night reminders (no more than twice a month).</x-form.checkbox>
                        <div class="sm:col-span-2"><x-button type="submit" variant="dark">Save details</x-button></div>
                    </form>
                </section>

                {{-- Password (Phase 2.5: PUT /user/password via Fortify; logs other devices out) --}}
                <section id="password" class="{{ $card }} scroll-mt-28" aria-labelledby="password-title">
                    <div class="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 id="password-title" class="text-lg font-extrabold text-navy-950">Password</h2>
                        @if ($user->password_changed_at)
                            <p class="text-xs text-navy-400">Last changed {{ $user->password_changed_at->setTimezone(config('app.display_timezone'))->format('M j, Y') }}</p>
                        @endif
                    </div>
                    @if ($status === 'password-updated')
                        <p class="{{ $ok }}" role="status">Password changed. Your other devices were logged out.</p>
                    @endif
                    <form method="POST" action="{{ route('user-password.update') }}" class="mt-6 grid gap-5 sm:grid-cols-2">
                        @csrf
                        @method('PUT')
                        <x-form.password name="current_password" id="f-current-password" label="Current password" :error="$passwordErrors->first('current_password')" autocomplete="current-password" maxlength="128" class="sm:col-span-2" />
                        <x-form.password name="password" label="New password" id="f-new-password" :error="$passwordErrors->first('password')" autocomplete="new-password" minlength="12" maxlength="128" hint="At least 12 characters, with upper- and lower-case letters and a number." />
                        <x-form.password name="password_confirmation" label="Confirm new password" id="f-new-password-confirmation" autocomplete="new-password" minlength="12" maxlength="128" data-match="f-new-password" />
                        <div class="sm:col-span-2"><x-button type="submit" variant="dark">Update password</x-button></div>
                    </form>
                </section>

                {{-- Two-factor (Phase 2.4: Fortify two-factor authentication, managed on its own page) --}}
                <section id="two-factor" class="{{ $card }} scroll-mt-28" aria-labelledby="tfa-title">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="tfa-title" class="text-lg font-extrabold text-navy-950">Two-factor login</h2>
                        <span @class(['rounded-full px-2.5 py-1 text-[11px] font-bold', 'bg-emerald-100 text-emerald-800' => $twoFactorOn, 'bg-navy-100 text-navy-600' => ! $twoFactorOn])>{{ $twoFactorOn ? 'On' : 'Off' }}</span>
                    </div>
                    <p class="mt-2 text-sm leading-relaxed text-navy-500">Add a second step when you log in: a 6-digit code from an authenticator app such as Google Authenticator or 1Password. {{ $isAdmin ? 'Recommended for the admin account.' : 'Boat owners need it before their first withdrawal.' }}</p>
                    <x-button :href="route('account.two-factor')" variant="outline" class="mt-5" icon-left="shield-check">{{ $twoFactorOn ? 'Manage two-factor login' : 'Turn on two-factor login' }}</x-button>
                </section>

                {{-- Devices (Phase 2.4: database sessions; "log out other devices" needs a recent password confirmation) --}}
                <section id="sessions" class="{{ $card }} scroll-mt-28" aria-labelledby="sessions-title">
                    <h2 id="sessions-title" class="text-lg font-extrabold text-navy-950">Devices</h2>
                    <p class="mt-2 text-sm text-navy-500">Where you're logged in. Don't recognise one? Log out of other devices and change your password.</p>
                    @if (session('sessions_status'))
                        <p class="mt-4 rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200" role="status">{{ session('sessions_status') }}</p>
                    @endif
                    <ul class="mt-5 divide-y divide-navy-100">
                        @foreach ($sessions as $session)
                            <li class="flex items-center gap-4 py-4">
                                <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-navy-50 text-navy-500"><i data-lucide="{{ $session->icon }}" class="h-5 w-5"></i></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm font-bold text-navy-950">{{ $session->device }}</p>
                                    <p class="text-xs text-navy-500">IP {{ $session->ip }} · {{ $session->current ? 'This device' : 'Last active '.$session->last_active->diffForHumans() }}</p>
                                </div>
                                @if ($session->current)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-[11px] font-bold text-emerald-800">Current</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    <form method="POST" action="{{ route('account.sessions.destroy-others') }}" class="mt-2">
                        @csrf
                        @method('DELETE')
                        <x-button type="submit" variant="outline">Log out other devices</x-button>
                    </form>
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

                {{-- Delete account (Phase 2.5: password in the dialog; personal data anonymized, booking records kept) --}}
                <section id="delete" class="{{ $card }} scroll-mt-28" aria-labelledby="delete-title">
                    <h2 id="delete-title" class="text-lg font-extrabold text-navy-950">Delete account</h2>
                    <p class="mt-2 text-sm leading-relaxed text-navy-500">We'll delete your profile and saved cards. Records of past bookings and payments are kept as long as the law requires. Upcoming trips must be cancelled first.</p>
                    @if ($deletionBlockers !== [])
                        <p class="mt-5 text-sm font-semibold text-navy-700">{{ $deletionBlockers[0] }}</p>
                    @else
                        <button type="button" data-open-delete class="mt-5 inline-flex items-center justify-center gap-2 rounded-full border border-red-200 px-6 py-3 text-sm font-bold text-red-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white">Delete my account</button>
                    @endif
                </section>
            </div>
        </div>
    </x-account.shell>
@endsection

@if ($deletionBlockers === [])
    @push('modals')
        <dialog id="delete-dialog" aria-labelledby="delete-dialog-title" @if ($errors->deleteAccount->any()) data-open-on-load @endif class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl bg-white p-0 text-navy-900 shadow-2xl backdrop:bg-navy-950/60">
            <form method="POST" action="{{ route('account.destroy') }}" class="p-6 md:p-8">
                @csrf
                @method('DELETE')
                <h2 id="delete-dialog-title" class="font-display text-3xl text-navy-950">Delete your account?</h2>
                <p class="mt-3 text-sm leading-relaxed text-navy-600">This can't be undone. Your profile, saved cards and login are removed, and you are logged out everywhere. Records of past bookings and payments are kept as the law requires. We'll email you a confirmation.</p>
                <x-form.password name="password" id="f-delete-password" label="Your password" :error="$errors->deleteAccount->first('password')" autocomplete="current-password" maxlength="128" class="mt-5" />
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button type="button" variant="outline" data-close-dialog>Keep my account</x-button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-red-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-red-700">Delete my account</button>
                </div>
            </form>
        </dialog>
    @endpush
@endif
