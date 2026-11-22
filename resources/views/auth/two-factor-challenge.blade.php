@extends('layouts.auth', ['title' => 'Two-factor login', 'page' => 'auth-two-factor'])

{{-- Fortify's challenge after a correct password (PLAN §8.1). A wrong recovery code comes back in recovery mode. --}}
@php($recovery = $errors->has('recovery_code'))

@section('form')
    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-brass-100 text-brass-700"><i data-lucide="shield-check" class="h-6 w-6"></i></span>
    <h1 class="mt-6 font-display text-4xl text-navy-950">Enter your code</h1>
    <p @class(['mt-2 text-sm text-navy-500', 'hidden' => $recovery]) data-tfa-text="code">Open your authenticator app and enter the 6-digit code for {{ config('site.business.name') }}.</p>
    <p @class(['mt-2 text-sm text-navy-500', 'hidden' => ! $recovery]) data-tfa-text="recovery">Enter one of the recovery codes you saved when you turned on two-factor login. Each code works once.</p>

    <form method="POST" action="{{ route('two-factor.login.store') }}" class="mt-8 space-y-5">
        @csrf
        <div @class(['hidden' => $recovery]) data-tfa-field="code">
            <x-form.input name="code" label="6-digit code" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required :autofocus="! $recovery" :disabled="$recovery" placeholder="123456" class="[&_input]:text-center [&_input]:font-mono [&_input]:text-lg [&_input]:tracking-[0.5em]" />
        </div>
        <div @class(['hidden' => ! $recovery]) data-tfa-field="recovery">
            <x-form.input name="recovery_code" label="Recovery code" autocomplete="off" maxlength="21" placeholder="abcd1-efgh2" required :autofocus="$recovery" :disabled="! $recovery" />
        </div>
        <x-button type="submit" size="block">Verify and log in</x-button>
    </form>

    <button type="button" data-tfa-switch class="mt-6 w-full text-center text-sm font-bold text-brass-600 hover:text-brass-700">{{ $recovery ? 'Use your authenticator code instead' : 'Use a recovery code instead' }}</button>
@endsection
