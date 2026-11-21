{{--
    Auth screens (login, register, password reset, email verification, 2FA, confirm password):
    slim header, photo panel on large screens, form on the right. Child views fill @section('form').
    Phase 2: Fortify renders these same views (Fortify::loginView(fn () => view('auth.login')) …).
--}}
@extends('layouts.focus', [
    'bodyClass' => 'bg-shell',
    'page' => $page ?? 'auth',
    'robots' => 'noindex',
    'mainClass' => 'pt-16',
])

@section('header')
    <header class="fixed inset-x-0 top-0 z-50 bg-navy-950 text-white shadow-lg shadow-navy-950/20">
        <div class="flex h-16 items-center justify-between gap-4 px-4 md:px-8">
            <x-logo size="sm" />
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-white/80 hover:text-white"><i data-lucide="arrow-left" class="h-4 w-4"></i>Back to site</a>
        </div>
    </header>
@endsection

@section('content')
    <div class="grid min-h-[calc(100svh-4rem)] lg:grid-cols-2">
        <section class="relative isolate hidden overflow-hidden bg-navy-950 text-white lg:flex lg:flex-col lg:justify-end">
            <img src="{{ asset($authImage ?? 'images/yacht-golden-hour.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover opacity-60">
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950 via-navy-950/60 to-navy-950/10"></div>
            <div class="p-12 xl:p-16">
                <p class="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.3em] text-brass-300"><span class="h-px w-10 bg-brass-400"></span>{{ config('site.business.name') }}</p>
                <p class="mt-4 max-w-md font-display text-4xl leading-tight">{{ $panelTitle ?? 'Your private day on Lake Michigan starts here.' }}</p>
                <ul class="mt-8 space-y-3 text-sm text-white/80">
                    <li class="flex items-center gap-3"><i data-lucide="ship-wheel" class="h-5 w-5 text-brass-300"></i>A licensed USCG captain on every trip</li>
                    <li class="flex items-center gap-3"><i data-lucide="shield-check" class="h-5 w-5 text-brass-300"></i>Secure payments — card details never touch our servers</li>
                    <li class="flex items-center gap-3"><i data-lucide="calendar-check" class="h-5 w-5 text-brass-300"></i>Manage trips, receipts and cancellations in one place</li>
                </ul>
            </div>
        </section>

        <section class="flex items-center justify-center px-5 py-12 sm:px-10">
            <div class="w-full max-w-md">
                @php
                    $status = session('status');
                    $status = is_string($status) ? (['verification-link-sent' => 'A new confirmation link is on its way to your inbox.'][$status] ?? $status) : null;
                @endphp
                @if ($status)
                    <p class="mb-6 rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200" role="status">{{ $status }}</p>
                @endif
                @yield('form')
            </div>
        </section>
    </div>
@endsection
