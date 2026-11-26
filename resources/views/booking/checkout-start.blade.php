@extends('layouts.app', [
    'title' => 'Secure checkout',
    'robots' => 'noindex', // one guest's trip (3.6)
    'headerSolid' => true,
    'mainClass' => 'pb-24 pt-28',
])

{{--
    Checkout start (PLAN 4.5): the trip from the boat page's card, before it is held. A guest logs in or creates an
    account right here (Fortify's own endpoints; afterwards they come back to this page); a signed-in guest
    continues, which holds the time (POST /checkout) and opens the checkout page. The price is QuoteService's.
--}}
@php
    $tz = config('app.display_timezone');
    $boat = $trip['boat'];
    $start = $trip['startsAt']->setTimezone($tz);
    $end = $start->addHours($trip['hours']);
    $cover = $yacht?->cover ?? App\Services\PublicCatalog::PLACEHOLDER;
    $harbor = $yacht?->harbor;
    $exact = fn (int $cents) => money($cents);
    $tabOn = 'flex-1 rounded-full bg-navy-950 px-5 py-2.5 text-sm font-bold text-white';
    $tabOff = 'flex-1 rounded-full px-5 py-2.5 text-sm font-bold text-navy-600 hover:text-navy-950';
    $signUp = old('first_name') !== null || $errors->has('first_name') || $errors->has('terms') || $errors->has('password_confirmation');
@endphp

@section('content')
    <section class="border-b border-navy-100 bg-white">
        <div class="container py-6">
            <ol class="flex items-center gap-2 text-xs font-bold sm:gap-3 sm:text-sm" aria-label="Progress">
                <li><a href="{{ $editUrl }}" class="flex items-center gap-2 text-navy-500 hover:text-navy-950"><span class="grid h-7 w-7 place-items-center rounded-full bg-navy-950 text-white"><i data-lucide="check" class="h-4 w-4"></i></span>Booking</a></li>
                <li class="h-px w-6 bg-navy-200 sm:w-10" aria-hidden="true"></li>
                <li class="flex items-center gap-2 text-navy-950" aria-current="step"><span class="grid h-7 w-7 place-items-center rounded-full bg-brass-400 text-navy-950">2</span>Checkout</li>
                <li class="h-px w-6 bg-navy-200 sm:w-10" aria-hidden="true"></li>
                <li class="flex items-center gap-2 text-navy-400"><span class="grid h-7 w-7 place-items-center rounded-full bg-navy-100">3</span>Confirmed</li>
            </ol>
        </div>
    </section>

    <section class="pt-8">
        <div class="container grid gap-8 lg:grid-cols-[minmax(0,1fr)_420px]">
            <div class="min-w-0 space-y-6">
                <div>
                    <h1 class="font-display text-4xl text-navy-950 md:text-5xl">Secure checkout</h1>
                    <p class="mt-2 text-navy-500">{{ auth()->check() ? 'Check your trip, then continue to pay.' : 'Log in or create a free account to book — it keeps your trips and receipts in one place.' }}</p>
                </div>

                @auth
                    <div class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                        <div class="flex items-center gap-4">
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-brass-100 font-extrabold text-brass-800">{{ auth()->user()->initials }}</span>
                            <div class="min-w-0"><p class="font-bold text-navy-950">Booking as {{ auth()->user()->name }}</p><p class="truncate text-sm text-navy-500">{{ auth()->user()->email }}</p></div>
                        </div>
                        <form method="POST" action="{{ route('checkout.hold') }}" class="mt-6">
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                            @foreach ($holdFields as $name => $value)
                                <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                            @endforeach
                            <x-button type="submit" icon="arrow-right" size="block">Continue to payment</x-button>
                        </form>
                        <p class="mt-4 flex items-center justify-center gap-2 text-xs text-navy-500"><i data-lucide="timer" class="h-4 w-4 text-brass-500"></i>We'll hold this time for you while you pay.</p>
                    </div>
                @else
                    <div class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                        <div class="flex gap-1 rounded-full bg-navy-50 p-1" role="tablist" aria-label="Log in or create an account" data-tabs data-tab-on="{{ $tabOn }}" data-tab-off="{{ $tabOff }}">
                            <button type="button" role="tab" id="tab-login" aria-controls="panel-login" aria-selected="{{ $signUp ? 'false' : 'true' }}" class="{{ $signUp ? $tabOff : $tabOn }}">Log in</button>
                            <button type="button" role="tab" id="tab-register" aria-controls="panel-register" aria-selected="{{ $signUp ? 'true' : 'false' }}" class="{{ $signUp ? $tabOn : $tabOff }}">Create an account</button>
                        </div>

                        <div id="panel-login" role="tabpanel" aria-labelledby="tab-login" @if ($signUp) hidden @endif>
                            <form method="POST" action="{{ url('/login') }}" class="mt-6 space-y-5">
                                @csrf
                                <x-form.input name="email" id="login-email" type="email" label="Email" required autocomplete="username" maxlength="254" placeholder="you@email.com" icon="mail" />
                                <x-form.password name="password" id="login-password" label="Password" autocomplete="current-password" maxlength="128">
                                    <x-slot:aside><a href="{{ route('password.request') }}" class="text-xs font-bold text-brass-600 hover:text-brass-700">Forgot password?</a></x-slot:aside>
                                </x-form.password>
                                <x-form.checkbox name="remember">Keep me logged in on this device</x-form.checkbox>
                                <x-button type="submit" size="block">Log in and continue</x-button>
                            </form>
                        </div>

                        <div id="panel-register" role="tabpanel" aria-labelledby="tab-register" @unless ($signUp) hidden @endunless>
                            <form method="POST" action="{{ url('/register') }}" class="mt-6 grid gap-5 sm:grid-cols-2">
                                @csrf
                                <x-form.input name="first_name" label="First name" required autocomplete="given-name" maxlength="60" />
                                <x-form.input name="last_name" label="Last name" required autocomplete="family-name" maxlength="60" />
                                <x-form.input name="email" id="register-email" type="email" label="Email" required autocomplete="email" maxlength="254" placeholder="you@email.com" class="sm:col-span-2" />
                                <x-form.input name="phone" type="tel" label="Mobile phone" autocomplete="tel" maxlength="30" placeholder="(312) 555-0100" hint="Your captain texts you before the trip." class="sm:col-span-2" />
                                <x-form.password name="password" id="register-password" label="Password" autocomplete="new-password" minlength="12" maxlength="128" hint="At least 12 characters, with upper- and lower-case letters and a number." class="sm:col-span-2" />
                                <x-form.password name="password_confirmation" id="register-password-confirmation" label="Confirm password" autocomplete="new-password" minlength="12" maxlength="128" data-match="register-password" class="sm:col-span-2" />
                                <x-form.checkbox name="terms" required class="sm:col-span-2">I agree to the <a href="{{ route('legal.terms') }}" target="_blank" class="font-bold text-navy-950 underline">Terms of Service</a> and the <a href="{{ route('legal.privacy') }}" target="_blank" class="font-bold text-navy-950 underline">Privacy Policy</a>.</x-form.checkbox>
                                <x-form.checkbox name="marketing" class="sm:col-span-2">Send me seasonal offers (optional).</x-form.checkbox>
                                <x-button type="submit" size="block" class="sm:col-span-2">Create account and continue</x-button>
                            </form>
                        </div>
                    </div>
                @endauth
            </div>

            {{-- Trip summary --}}
            <aside class="order-first lg:order-none">
                <div class="lg:sticky lg:top-28 lg:-mx-4 lg:-mt-4 lg:max-h-[calc(100dvh-8rem)] lg:overflow-y-auto lg:px-4 lg:pt-4 lg:pb-4 no-scrollbar" data-sticky-panel>
                    <div class="overflow-hidden rounded-[2rem] bg-white shadow-card ring-1 ring-navy-100">
                        <div class="relative h-40 overflow-hidden">
                            <img src="{{ asset($cover) }}" alt="{{ $boat->title }}" class="h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-navy-950 via-navy-950/30 to-transparent"></div>
                            <div class="absolute bottom-4 left-5 right-5 flex items-end justify-between text-white">
                                <div><p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brass-300">{{ $trip['experience']?->name ?? 'Private charter' }}</p><p class="font-display text-2xl">{{ $boat->title }}</p></div>
                                <a href="{{ $editUrl }}" class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold backdrop-blur hover:bg-white hover:text-navy-950">Edit<span class="sr-only"> booking</span></a>
                            </div>
                        </div>
                        <dl class="grid grid-cols-2 gap-px bg-navy-100 text-sm">
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Date</dt><dd class="mt-1 font-bold text-navy-950">{{ $start->format('D, M j, Y') }}</dd></div>
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Time</dt><dd class="mt-1 font-bold text-navy-950">{{ $start->format('g:i A') }} – {{ $end->format('g:i A') }}</dd></div>
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Guests</dt><dd class="mt-1 font-bold text-navy-950">{{ $trip['guests'] }} {{ Str::plural('guest', $trip['guests']) }}</dd></div>
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Departs</dt><dd class="mt-1 font-bold text-navy-950">{{ $harbor?->name ?? 'Chicago' }}</dd></div>
                        </dl>
                        <div class="border-t border-navy-100 p-5">
                            <dl class="space-y-2.5 text-sm">
                                @foreach ([...$quote->lines, ...$quote->adjustments] as $line)
                                    <div class="flex justify-between gap-4"><dt class="text-navy-600">{{ $line->label }}<span class="block text-[11px] text-navy-400">{{ $line->description }}</span></dt><dd class="font-semibold text-navy-900">{{ $exact($line->amountCents) }}</dd></div>
                                @endforeach
                            </dl>
                            <div class="mt-4 flex items-end justify-between border-t border-navy-200 pt-4">
                                <p class="font-bold text-navy-950">Total</p>
                                <p class="text-2xl font-extrabold text-navy-950">{{ $exact($quote->totalCents) }}</p>
                            </div>
                            <p class="mt-2 text-xs text-navy-500">Paid in full at checkout. Add-ons, gratuity and promo codes are on the next step.</p>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </section>
@endsection
