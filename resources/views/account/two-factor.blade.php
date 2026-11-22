@extends('layouts.app', [
    'title' => 'Two-factor login',
    'page' => 'account-two-factor',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@php
    $card = 'rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-8';
    $pill = 'rounded-full px-2.5 py-1 text-[11px] font-bold';
@endphp

@section('content')
    <x-account.shell active="profile" heading="Two-factor login" intro="A second step when you log in: a 6-digit code from an authenticator app such as Google Authenticator, Microsoft Authenticator or 1Password." :back="['Profile & security', route('account.profile')]">
        <div class="mt-8 max-w-3xl space-y-6">
            @if ($message)
                <p class="rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200" role="status">{{ $message }}</p>
            @endif

            @if ($state === 'off')
                <section class="{{ $card }}" aria-labelledby="tfa-off-title">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="tfa-off-title" class="text-lg font-extrabold text-navy-950">Two-factor login is off</h2>
                        <span class="{{ $pill }} bg-navy-100 text-navy-600">Off</span>
                    </div>
                    <ol class="mt-5 list-decimal space-y-2 pl-5 text-sm leading-relaxed text-navy-600">
                        <li>Install an authenticator app on your phone, if you don't have one yet.</li>
                        <li>Press the button below, then scan the QR code with the app.</li>
                        <li>Enter the 6-digit code the app shows, and save your recovery codes.</li>
                    </ol>
                    <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-6">
                        @csrf
                        <input type="hidden" name="force" value="1">
                        <x-button type="submit" variant="dark" icon-left="shield-check">Turn on two-factor login</x-button>
                    </form>
                </section>
            @elseif ($state === 'pending')
                <section class="{{ $card }}" aria-labelledby="tfa-setup-title">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="tfa-setup-title" class="text-lg font-extrabold text-navy-950">Finish turning it on</h2>
                        <span class="{{ $pill }} bg-amber-100 text-amber-800">Almost done</span>
                    </div>

                    {{-- Shown at its real size (whole pixels per module, crisp at 100% zoom); the white quiet zone is inside
                         the frame, so a scan selection around the frame reads it. Only very narrow phones scale it down. --}}
                    <div class="mt-6 grid gap-6 sm:grid-cols-[auto_minmax(0,1fr)] sm:items-start">
                        <img src="{{ $qrCode['src'] }}" alt="QR code for your authenticator app" width="{{ $qrCode['size'] }}" height="{{ $qrCode['size'] }}" class="h-auto max-w-full rounded-xl ring-1 ring-navy-200">
                        <div class="min-w-0">
                            <p class="text-sm font-bold text-navy-950">1. Scan this QR code</p>
                            <p class="mt-1 text-sm leading-relaxed text-navy-500">In your authenticator app, add an account and scan the code. Can't scan it? Type this setup key instead:</p>
                            <p class="mt-3 break-all rounded-xl bg-navy-50 px-4 py-3 font-mono text-sm font-bold tracking-wider text-navy-950" data-setup-key>{{ $setupKey }}</p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-8">
                        @csrf
                        <p class="text-sm font-bold text-navy-950">2. Enter the 6-digit code from the app</p>
                        <x-form.input name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus placeholder="123456"
                            :error="$errors->confirmTwoFactorAuthentication->first('code')"
                            class="mt-4 max-w-xs [&_input]:text-center [&_input]:font-mono [&_input]:text-lg [&_input]:tracking-[0.5em]" />
                        <div class="mt-5 flex flex-wrap items-center gap-4">
                            <x-button type="submit" variant="dark">Confirm and turn on</x-button>
                            <a href="{{ route('account.profile') }}" class="text-sm font-bold text-navy-500 hover:text-navy-950">Cancel</a>
                        </div>
                    </form>
                </section>
            @else
                <section class="{{ $card }}" aria-labelledby="tfa-on-title">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <h2 id="tfa-on-title" class="text-lg font-extrabold text-navy-950">Two-factor login is on</h2>
                        <span class="{{ $pill }} bg-emerald-100 text-emerald-800">On</span>
                    </div>
                    <p class="mt-2 text-sm text-navy-500">Turned on {{ $confirmedAt?->setTimezone(config('app.display_timezone'))->format('M j, Y') }}. Each login asks for a code from your app.</p>
                    @if ($isAdmin && $showCodes)
                        <x-button :href="route('admin.settings').'#security'" variant="dark" class="mt-5" icon="arrow-right">Back to admin settings</x-button>
                    @endif
                </section>

                <section id="recovery-codes" class="{{ $card }} scroll-mt-28" aria-labelledby="codes-title">
                    <h2 id="codes-title" class="text-lg font-extrabold text-navy-950">Recovery codes</h2>
                    <p class="mt-2 text-sm leading-relaxed text-navy-500">Lost your phone? Each code lets you log in once instead of a code from the app. Keep them somewhere safe, like a password manager.</p>
                    <details class="mt-5" @if ($showCodes) open @endif>
                        <summary class="cursor-pointer text-sm font-bold text-brass-600 hover:text-brass-700">Show recovery codes</summary>
                        <ul class="mt-4 grid grid-cols-1 gap-2 rounded-2xl bg-navy-50 p-4 font-mono text-sm font-bold text-navy-950 sm:grid-cols-2" aria-label="Recovery codes">
                            @foreach ($recoveryCodes as $code)
                                <li>{{ $code }}</li>
                            @endforeach
                        </ul>
                    </details>
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}" class="mt-5">
                        @csrf
                        <x-button type="submit" variant="outline" icon-left="key-round">Get new recovery codes</x-button>
                    </form>
                    <p class="mt-2 text-xs text-navy-400">New codes replace the old ones straight away.</p>
                </section>

                <section class="{{ $card }}" aria-labelledby="tfa-disable-title">
                    <h2 id="tfa-disable-title" class="text-lg font-extrabold text-navy-950">Turn off two-factor login</h2>
                    <p class="mt-2 text-sm leading-relaxed text-navy-500">Your account will be protected by your password only. {{ $isAdmin ? 'We recommend keeping it on for the admin account on the live site.' : 'Boat owners need two-factor login to withdraw earnings.' }}</p>
                    <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-5">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full border border-red-200 px-6 py-3 text-sm font-bold text-red-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white">Turn off two-factor login</button>
                    </form>
                </section>
            @endif
        </div>
    </x-account.shell>
@endsection
