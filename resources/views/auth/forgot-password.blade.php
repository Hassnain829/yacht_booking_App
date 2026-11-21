@extends('layouts.auth', ['title' => 'Forgot password'])

@section('form')
    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-brass-100 text-brass-700"><i data-lucide="key-round" class="h-6 w-6"></i></span>
    <h1 class="mt-6 font-display text-4xl text-navy-950">Forgot your password?</h1>
    <p class="mt-2 text-sm leading-relaxed text-navy-500">Enter the email you use for {{ config('site.business.name') }}. If it has an account, we'll send a link to choose a new password. The link works for 60 minutes.</p>

    <form method="POST" action="{{ url('/forgot-password') }}" class="mt-8 space-y-5">
        @csrf
        <x-form.input name="email" type="email" label="Email" required autofocus autocomplete="email" maxlength="254" placeholder="you@email.com" icon="mail" />
        <x-button type="submit" size="block">Email me a reset link</x-button>
    </form>

    <p class="mt-8 text-center text-sm text-navy-500"><a href="{{ route('login') }}" class="inline-flex items-center gap-1.5 font-bold text-navy-950 underline-offset-2 hover:underline"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back to log in</a></p>
@endsection
