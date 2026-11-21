@extends('layouts.auth', ['title' => 'Choose a new password'])

@section('form')
    <h1 class="font-display text-4xl text-navy-950">Choose a new password</h1>
    <p class="mt-2 text-sm text-navy-500">After this, you'll be logged out on every other device.</p>

    <form method="POST" action="{{ url('/reset-password') }}" class="mt-8 space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $token ?? '' }}">
        <x-form.input name="email" type="email" label="Email" :value="$email ?? null" required autocomplete="email" maxlength="254" />
        <x-form.password name="password" id="f-reset-password" label="New password" autocomplete="new-password" minlength="12" maxlength="128" autofocus hint="At least 12 characters, with upper- and lower-case letters and a number." />
        <x-form.password name="password_confirmation" label="Confirm new password" autocomplete="new-password" minlength="12" maxlength="128" data-match="f-reset-password" />
        <x-button type="submit" size="block">Save new password</x-button>
    </form>
@endsection
