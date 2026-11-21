@extends('layouts.auth', ['title' => 'Create an account', 'authImage' => 'images/deck-lounge.jpg', 'panelTitle' => 'One account for booking trips and listing your boat.'])

@section('form')
    <h1 class="font-display text-4xl text-navy-950">Create your account</h1>
    <p class="mt-2 text-sm text-navy-500">Book faster, keep your receipts in one place — and list your boat any time with the same account.</p>

    <form method="POST" action="{{ url('/register') }}" class="mt-8 grid gap-5 sm:grid-cols-2">
        @csrf
        <x-form.input name="first_name" label="First name" required autofocus autocomplete="given-name" maxlength="60" />
        <x-form.input name="last_name" label="Last name" required autocomplete="family-name" maxlength="60" />
        <x-form.input name="email" type="email" label="Email" required autocomplete="email" maxlength="254" placeholder="you@email.com" class="sm:col-span-2" />
        <x-form.input name="phone" type="tel" label="Mobile phone" autocomplete="tel" maxlength="30" placeholder="(312) 555-0100" hint="Optional — your captain texts you before a trip." class="sm:col-span-2" />
        <x-form.password name="password" id="f-register-password" label="Password" autocomplete="new-password" minlength="12" maxlength="128" hint="At least 12 characters, with upper- and lower-case letters and a number." class="sm:col-span-2" />
        <x-form.password name="password_confirmation" label="Confirm password" autocomplete="new-password" minlength="12" maxlength="128" data-match="f-register-password" class="sm:col-span-2" />
        <x-form.checkbox name="terms" required class="sm:col-span-2">I agree to the <a href="{{ route('legal.terms') }}" target="_blank" class="font-bold text-navy-950 underline">Terms of Service</a> and the <a href="{{ route('legal.privacy') }}" target="_blank" class="font-bold text-navy-950 underline">Privacy Policy</a>.</x-form.checkbox>
        <x-form.checkbox name="marketing" class="sm:col-span-2">Send me seasonal offers (optional).</x-form.checkbox>
        <x-button type="submit" size="block" class="sm:col-span-2">Create account</x-button>
    </form>

    <p class="mt-8 text-center text-sm text-navy-500">Already have an account? <a href="{{ route('login') }}" class="font-bold text-navy-950 underline-offset-2 hover:underline">Log in</a></p>
@endsection
