@extends('layouts.auth', ['title' => 'Confirm your email'])

@section('form')
    <span class="grid h-14 w-14 place-items-center rounded-2xl bg-brass-100 text-brass-700"><i data-lucide="mail-check" class="h-6 w-6"></i></span>
    <h1 class="mt-6 font-display text-4xl text-navy-950">Check your inbox</h1>
    <p class="mt-2 text-sm leading-relaxed text-navy-500">We sent a confirmation link to <strong class="text-navy-950">{{ auth()->user()?->email ?? 'your email' }}</strong>. Click it to finish setting up your account — then you can book trips and list boats.</p>
    <p class="mt-4 text-sm leading-relaxed text-navy-500">Can't find it? Check your spam folder, or send a new link.</p>

    <div class="mt-8 flex flex-col gap-3 sm:flex-row">
        <form method="POST" action="{{ url('/email/verification-notification') }}" class="flex-1">
            @csrf
            <x-button type="submit" size="block">Send a new link</x-button>
        </form>
        <form method="POST" action="{{ url('/logout') }}">
            @csrf
            <x-button type="submit" variant="outline" size="block">Log out</x-button>
        </form>
    </div>
@endsection
