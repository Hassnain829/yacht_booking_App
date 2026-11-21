@extends('layouts.auth', ['title' => 'Confirm your password'])

@section('form')
    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-brass-100 text-brass-700"><i data-lucide="lock" class="h-6 w-6"></i></span>
    <h1 class="mt-6 font-display text-4xl text-navy-950">Confirm it's you</h1>
    <p class="mt-2 text-sm leading-relaxed text-navy-500">This is a secure area (payouts, security settings or a sensitive admin action). Please enter your password to continue — we won't ask again for a few hours.</p>

    <form method="POST" action="{{ url('/user/confirm-password') }}" class="mt-8 space-y-5" data-demo-form="Demo: password confirmation goes live in Phase 2.">
        @csrf
        <x-form.password name="password" label="Password" autocomplete="current-password" maxlength="128" autofocus />
        <x-button type="submit" size="block">Confirm</x-button>
    </form>
@endsection
