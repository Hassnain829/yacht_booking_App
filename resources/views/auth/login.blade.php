@extends('layouts.auth', ['title' => 'Log in'])

@section('form')
    <h1 class="font-display text-4xl text-navy-950">Welcome back</h1>
    <p class="mt-2 text-sm text-navy-500">Log in to see your trips, or to manage your boats if you're an owner.</p>

    <form method="POST" action="{{ url('/login') }}" class="mt-8 space-y-5">
        @csrf
        <x-form.input name="email" type="email" label="Email" required autofocus autocomplete="username" maxlength="254" placeholder="you@email.com" icon="mail" />
        <x-form.password name="password" label="Password" autocomplete="current-password" maxlength="128">
            <x-slot:aside><a href="{{ route('password.request') }}" class="text-xs font-bold text-brass-600 hover:text-brass-700">Forgot password?</a></x-slot:aside>
        </x-form.password>
        <x-form.checkbox name="remember">Keep me logged in on this device</x-form.checkbox>
        <x-button type="submit" size="block">Log in</x-button>
    </form>

    <p class="mt-8 text-center text-sm text-navy-500">New to {{ config('site.business.name') }}? <a href="{{ route('register') }}" class="font-bold text-navy-950 underline-offset-2 hover:underline">Create an account</a></p>
@endsection
