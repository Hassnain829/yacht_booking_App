@extends('layouts.app', [
    'title' => 'Secure checkout',
    'page' => 'checkout',
    'robots' => 'noindex', // one visitor's booking (3.6)
    'headerSolid' => true,
    'mainClass' => 'pb-24 pt-28',
])

@php
    $harbor = $yacht->harbor;
    $end = \Carbon\CarbonImmutable::createFromFormat('H:i', $selection->time)->addHours($selection->hours);
    $fmtTime = fn (string $t) => \Carbon\CarbonImmutable::createFromFormat('H:i', $t)->format('g:i A');
    $fmtDate = fn (string $d, string $f = 'D, M j, Y') => \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $d)->format($f);
    $whole = fn (int $c) => money($c, 'never');
    $base = $quote['charter'] + $quote['captain'];
    $fieldErrorClass = 'mt-2 w-full rounded-xl border bg-white px-4 py-3.5 text-sm focus:outline-hidden focus:ring-4';
    $selectClass = 'mt-2 w-full rounded-xl border border-navy-200 bg-white px-4 py-3.5 text-sm focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
    $cardField = 'w-full rounded-xl border border-navy-200 px-4 py-3.5 text-sm focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
@endphp

@section('content')
    <section class="border-b border-navy-100 bg-white">
        <div class="container flex flex-col gap-4 py-6 md:flex-row md:items-center md:justify-between">
            <ol class="flex items-center gap-2 text-xs font-bold sm:gap-3 sm:text-sm" aria-label="Progress">
                <li><a href="{{ $editUrl }}" class="flex items-center gap-2 text-navy-500 hover:text-navy-950"><span class="grid h-7 w-7 place-items-center rounded-full bg-navy-950 text-white"><i data-lucide="check" class="h-4 w-4"></i></span>Booking</a></li>
                <li class="h-px w-6 bg-navy-200 sm:w-10" aria-hidden="true"></li>
                <li class="flex items-center gap-2 text-navy-950" aria-current="step"><span class="grid h-7 w-7 place-items-center rounded-full bg-brass-400 text-navy-950">2</span>Checkout</li>
                <li class="h-px w-6 bg-navy-200 sm:w-10" aria-hidden="true"></li>
                <li class="flex items-center gap-2 text-navy-400"><span class="grid h-7 w-7 place-items-center rounded-full bg-navy-100">3</span>Confirmed</li>
            </ol>
            <p class="inline-flex items-center gap-2 self-start rounded-full bg-brass-50 px-4 py-2 text-xs font-bold text-brass-800 ring-1 ring-brass-200 md:self-auto">
                <i data-lucide="timer" class="h-4 w-4"></i>We're holding your time slot for <span id="hold-timer" class="tabular-nums">15:00</span>
            </p>
        </div>
    </section>

    @if ($isDemo)
        <div class="container mt-6">
            <div class="flex flex-col gap-3 rounded-2xl bg-navy-950 px-5 py-4 text-sm text-white sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-center gap-2"><i data-lucide="info" class="h-4 w-4 text-brass-300"></i>Showing a sample booking for the demo.</p>
                <a href="{{ route('yachts.index') }}" class="font-bold text-brass-300 hover:underline">Start your own booking →</a>
            </div>
        </div>
    @endif

    @error('booking')
        <div class="container mt-6" role="alert">
            <div class="flex items-center gap-3 rounded-2xl bg-red-50 px-5 py-4 text-sm font-semibold text-red-700 ring-1 ring-red-200"><i data-lucide="circle-alert" class="h-5 w-5 shrink-0"></i>{{ $message }} <a href="{{ route('yachts.index') }}" class="underline">Start again</a></div>
        </div>
    @enderror

    <section class="pt-8">
        <form id="checkout-form" action="{{ route('checkout.store') }}" method="post" class="container grid gap-8 lg:grid-cols-[minmax(0,1fr)_420px]" novalidate
              data-checkout="{{ json_encode($checkoutData) }}">
            @csrf
            @foreach (['experience', 'yacht', 'guests', 'date', 'time', 'hours'] as $field)
                <input type="hidden" name="{{ $field }}" value="{{ $selection->$field }}">
            @endforeach
            <input type="hidden" name="promo" value="{{ old('promo') }}">
            <input type="hidden" name="card_brand" value="Card">
            <input type="hidden" name="card_last4" value="">

            <div class="min-w-0 space-y-6">
                <div>
                    <h1 class="font-display text-4xl text-navy-950 md:text-5xl">Secure checkout</h1>
                    <p class="mt-2 text-navy-500">You're almost on the water. Review your details and confirm below.</p>
                </div>

                {{-- 1. Contact --}}
                <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                    <legend class="sr-only">Contact details</legend>
                    <div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-full bg-navy-950 text-xs font-extrabold text-white" aria-hidden="true">1</span><h2 class="text-xl font-extrabold text-navy-950">Lead guest details</h2></div>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <x-form.input name="first_name" label="First name" required maxlength="60" autocomplete="given-name" />
                        <x-form.input name="last_name" label="Last name" required maxlength="60" autocomplete="family-name" />
                        <x-form.input name="email" type="email" label="Email" required maxlength="254" autocomplete="email" placeholder="you@email.com" />
                        <x-form.input name="phone" type="tel" label="Mobile phone" required maxlength="30" autocomplete="tel" placeholder="(312) 000-0000" />
                    </div>
                    <p class="mt-4 flex items-center gap-2 text-xs text-navy-500"><i data-lucide="message-circle" class="h-4 w-4 text-brass-500"></i>Your captain will text this number the day before your charter.</p>
                </fieldset>

                {{-- 2. Trip details --}}
                <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                    <legend class="sr-only">Trip details</legend>
                    <div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-full bg-navy-950 text-xs font-extrabold text-white" aria-hidden="true">2</span><h2 class="text-xl font-extrabold text-navy-950">Trip details</h2></div>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <x-form.select name="occasion" label="Occasion" :options="array_combine(App\Support\Demo\BookingSelection::OCCASIONS, App\Support\Demo\BookingSelection::OCCASIONS)" :value="$selection->occasion" placeholder="Select (optional)" />
                        <x-form.select name="heard" label="How did you hear about us?" :options="array_combine(App\Http\Requests\CheckoutRequest::HEARD, App\Http\Requests\CheckoutRequest::HEARD)" placeholder="Select (optional)" />
                        <x-form.textarea name="notes" label="Notes for your captain" rows="3" maxlength="1000" class="sm:col-span-2" placeholder="Dietary needs, surprises, accessibility…" />
                    </div>
                </fieldset>

                {{-- 3. Add-ons: all optional (user decision 2026-11-25). The experience's suggested extras come first;
                     nothing is charged unless ticked. The totals update as they are ticked (checkout.js). --}}
                @if ($addonChoices->isNotEmpty())
                    <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                        <legend class="sr-only">Add-ons (optional)</legend>
                        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                            <div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-full bg-navy-950 text-xs font-extrabold text-white" aria-hidden="true">3</span><h2 class="text-xl font-extrabold text-navy-950">Make it yours</h2></div>
                            <p class="text-xs font-semibold text-navy-500">Optional · add as many as you like, or none</p>
                        </div>
                        <div class="mt-6 grid gap-3 md:grid-cols-2">
                            @foreach ($addonChoices as $addon)
                                <label class="group relative flex cursor-pointer items-start gap-4 rounded-2xl bg-white p-4 text-left ring-2 ring-navy-100 transition hover:ring-navy-300 has-[:checked]:bg-brass-50 has-[:checked]:ring-brass-400 has-[:focus-visible]:ring-navy-950">
                                    <input type="checkbox" name="addons[]" value="{{ $addon->slug }}" class="sr-only" @checked(in_array($addon->slug, old('addons', $selection->addons), true))>
                                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-navy-50 text-navy-600 group-has-[:checked]:bg-brass-400 group-has-[:checked]:text-navy-950"><i data-lucide="{{ $addon->icon }}" class="h-5 w-5"></i></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-bold text-navy-950">{{ $addon->name }}</span>
                                        @if ($addon->suggested && $experience)
                                            <span class="mt-1 inline-block rounded-md bg-brass-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-brass-800">Suggested for {{ $experience->name }}</span>
                                        @endif
                                        <span class="mt-0.5 block text-xs text-navy-500">{{ $addon->description }}</span>
                                        <span class="mt-1.5 block text-sm font-extrabold text-navy-950">{{ $whole($addon->price_cents) }}<span class="text-[11px] font-semibold text-navy-400">{{ $addon->unit === 'per_guest' ? ' per guest' : ' per trip' }}</span></span>
                                    </span>
                                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-transparent ring-2 ring-navy-200 group-has-[:checked]:bg-navy-950 group-has-[:checked]:text-white group-has-[:checked]:ring-navy-950"><i data-lucide="check" class="h-4 w-4"></i></span>
                                </label>
                            @endforeach
                        </div>
                        @error('addons')<p data-field-error class="mt-3 text-xs font-semibold text-red-600">{{ $message }}</p>@enderror
                    </fieldset>
                @endif

                {{-- 4. Gratuity --}}
                <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                    <legend class="sr-only">Crew gratuity</legend>
                    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-full bg-navy-950 text-xs font-extrabold text-white" aria-hidden="true">{{ $addonChoices->isNotEmpty() ? 4 : 3 }}</span><h2 class="text-xl font-extrabold text-navy-950">Crew gratuity</h2></div>
                        <p class="text-xs font-semibold text-navy-500">Optional · 100% goes to your captain &amp; crew</p>
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($pricing['gratuity_options'] as $pct)
                            <label class="group cursor-pointer rounded-2xl bg-white px-4 py-4 text-center ring-2 ring-navy-100 transition hover:ring-navy-300 has-[:checked]:bg-navy-950 has-[:checked]:text-white has-[:checked]:ring-navy-950 has-[:focus-visible]:ring-brass-400">
                                <input type="radio" name="gratuity" value="{{ $pct }}" class="sr-only" @checked((int) old('gratuity', $pricing['default_gratuity']) === $pct)>
                                <span class="block text-lg font-extrabold">{{ $pct === 0 ? 'No tip' : $pct.'%' }}</span>
                                <span class="block text-xs text-navy-400 group-has-[:checked]:text-brass-300" data-tip-amount="{{ $pct }}">{{ $pct === 0 ? 'Tip in cash' : $whole((int) round($base * $pct / 100)) }}</span>
                                @if ($pct === $pricing['default_gratuity'])
                                    <span class="mt-1 block text-[10px] font-bold uppercase tracking-wider text-brass-600 group-has-[:checked]:text-white/60">Most chosen</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- 5. Payment --}}
                <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                    <legend class="sr-only">Payment</legend>
                    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-full bg-navy-950 text-xs font-extrabold text-white" aria-hidden="true">{{ $addonChoices->isNotEmpty() ? 5 : 4 }}</span><h2 class="text-xl font-extrabold text-navy-950">Payment</h2></div>
                        <p class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700"><i data-lucide="lock" class="h-3.5 w-3.5"></i>256-bit SSL encrypted</p>
                    </div>

                    <p class="mt-6 flex items-start gap-3 rounded-2xl bg-brass-50 p-4 text-sm text-navy-700 ring-1 ring-brass-200"><i data-lucide="circle-check" class="mt-0.5 h-5 w-5 shrink-0 text-brass-600"></i><span><strong class="text-navy-950">Paid in full today.</strong> Your yacht is booked as soon as the payment goes through. Nothing more is charged before your trip.</span></p>

                    {{-- Phase 5: Stripe Payment Element replaces these fields. They have NO name attribute, so card data is never sent to our server. --}}
                    <div class="mt-6 grid gap-5 sm:grid-cols-6">
                        <div class="sm:col-span-6">
                            <label for="card-number" class="text-sm font-bold text-navy-800">Card number<span class="text-red-600" aria-hidden="true"> *</span></label>
                            <div class="relative mt-2">
                                <input id="card-number" inputmode="numeric" autocomplete="cc-number" placeholder="1234 5678 9012 3456" maxlength="19" required data-card="number" class="{{ $cardField }} pr-20 tracking-wider">
                                <span id="card-brand" class="absolute right-3 top-1/2 -translate-y-1/2 rounded-md bg-navy-50 px-2 py-1 text-[10px] font-extrabold uppercase tracking-wider text-navy-400">Card</span>
                            </div>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="card-exp" class="text-sm font-bold text-navy-800">Expiry<span class="text-red-600" aria-hidden="true"> *</span></label>
                            <div class="relative mt-2"><input id="card-exp" inputmode="numeric" autocomplete="cc-exp" placeholder="MM / YY" maxlength="7" required data-card="exp" class="{{ $cardField }}"></div>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="card-cvc" class="text-sm font-bold text-navy-800">CVC<span class="text-red-600" aria-hidden="true"> *</span></label>
                            <div class="relative mt-2"><input id="card-cvc" inputmode="numeric" autocomplete="cc-csc" placeholder="123" maxlength="4" required data-card="cvc" class="{{ $cardField }}"></div>
                        </div>
                        <div class="sm:col-span-2">
                            <label for="card-zip" class="text-sm font-bold text-navy-800">ZIP<span class="text-red-600" aria-hidden="true"> *</span></label>
                            <div class="relative mt-2"><input id="card-zip" inputmode="numeric" autocomplete="postal-code" placeholder="60601" maxlength="10" required data-card="zip" class="{{ $cardField }}"></div>
                        </div>
                    </div>
                    <p class="mt-4 rounded-xl bg-navy-50 px-4 py-3 text-xs text-navy-500"><strong class="text-navy-800">Demo mode:</strong> no real payment is taken. Use card <button type="button" id="fill-demo" class="font-bold text-brass-700 underline">4242 4242 4242 4242</button>, any future expiry and any CVC.</p>
                </fieldset>

                {{-- Agreement --}}
                <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                    <legend class="sr-only">Agreements</legend>
                    <x-form.checkbox name="agree" required>I agree to the <a href="{{ route('legal.cancellation') }}" target="_blank" class="font-bold text-navy-950 underline">Cancellation &amp; Weather Policy</a> and the passenger safety waiver, and confirm all guests will follow the captain's instructions.<span class="text-red-600" aria-hidden="true"> *</span></x-form.checkbox>
                    <x-form.checkbox name="marketing" class="mt-4">Send me seasonal deals and fireworks-night alerts (no spam, unsubscribe anytime).</x-form.checkbox>
                    <button type="submit" id="pay-btn" class="mt-8 inline-flex w-full items-center justify-center gap-3 rounded-full bg-brass-400 px-8 py-5 text-base font-extrabold text-navy-950 shadow-glow transition hover:-translate-y-0.5 hover:bg-brass-300 disabled:cursor-wait disabled:opacity-80">
                        <i data-lucide="lock" class="h-5 w-5"></i><span id="pay-label">Pay {{ $whole($quote['total']) }} &amp; confirm booking</span>
                    </button>
                    <p class="mt-4 text-center text-xs text-navy-400">You'll get an instant confirmation email with your booking details and dock directions.</p>
                </fieldset>
            </div>

            {{-- Order summary --}}
            <aside class="order-first lg:order-none">
                <div class="lg:sticky lg:top-28 lg:-mx-4 lg:-mt-4 lg:max-h-[calc(100dvh-8rem)] lg:overflow-y-auto lg:px-4 lg:pt-4 lg:pb-4 no-scrollbar" data-sticky-panel>
                    <div class="overflow-hidden rounded-[2rem] bg-white shadow-card ring-1 ring-navy-100" aria-live="polite">
                        <div class="relative h-40 overflow-hidden">
                            <img src="{{ asset($yacht->cover) }}" alt="{{ $yacht->title }}" class="h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-navy-950 via-navy-950/30 to-transparent"></div>
                            <div class="absolute bottom-4 left-5 right-5 flex items-end justify-between text-white">
                                <div><p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brass-300">{{ $experience?->name ?? 'Private charter' }}</p><p class="font-display text-2xl">{{ $yacht->title }}</p></div>
                                <a href="{{ $editUrl }}" class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold backdrop-blur hover:bg-white hover:text-navy-950">Edit<span class="sr-only"> booking</span></a>
                            </div>
                        </div>
                        <dl class="grid grid-cols-2 gap-px bg-navy-100 text-sm">
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Date</dt><dd class="mt-1 font-bold text-navy-950">{{ $fmtDate($selection->date) }}</dd></div>
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Time</dt><dd class="mt-1 font-bold text-navy-950">{{ $fmtTime($selection->time) }} – {{ $end->format('g:i A') }}</dd></div>
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Guests</dt><dd class="mt-1 font-bold text-navy-950">{{ $selection->guests }} guests</dd></div>
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Departs</dt><dd class="mt-1 font-bold text-navy-950">{{ $harbor?->name }}</dd></div>
                        </dl>

                        <div class="border-t border-navy-100 p-5">
                            <div id="promo-box">
                                <div class="flex gap-2" data-promo-form>
                                    <label for="promo-input" class="sr-only">Promo code</label>
                                    <input id="promo-input" maxlength="30" autocomplete="off" placeholder="Promo code" class="min-w-0 flex-1 rounded-xl border border-navy-200 px-4 py-3 text-sm uppercase placeholder:normal-case focus:border-navy-950 focus:outline-hidden">
                                    <button type="button" data-apply-promo class="rounded-xl bg-navy-950 px-5 text-sm font-bold text-white hover:bg-navy-800">Apply</button>
                                </div>
                                <p class="mt-2 text-[11px] text-navy-400" data-promo-hint>Try <strong>WELCOME10</strong> for 10% off the charter.</p>
                                <div class="hidden items-center justify-between rounded-xl bg-emerald-50 px-4 py-3 text-sm ring-1 ring-emerald-200" data-promo-applied>
                                    <span class="flex items-center gap-2 font-bold text-emerald-800"><i data-lucide="tag" class="h-4 w-4"></i><span data-promo-label></span></span>
                                    <button type="button" data-remove-promo class="text-xs font-bold text-emerald-800 hover:underline">Remove</button>
                                </div>
                            </div>

                            <dl id="checkout-lines" class="mt-5 space-y-2.5 text-sm">
                                @foreach ($quote['lines'] as $line)
                                    <div class="flex justify-between gap-4"><dt class="text-navy-600">{{ $line['label'] }}<span class="block text-[11px] text-navy-400">{{ $line['sub'] }}</span></dt><dd class="font-semibold text-navy-900">{{ $whole($line['amount']) }}</dd></div>
                                @endforeach
                                <div class="flex justify-between gap-4"><dt class="text-navy-600">Taxes (est. {{ App\Support\Money::percent($pricing['tax_rate_bp']) }})</dt><dd class="font-semibold text-navy-900">{{ $whole($quote['tax']) }}</dd></div>
                                <div class="flex justify-between gap-4"><dt class="text-navy-600">Crew gratuity ({{ $quote['gratuityPct'] }}%)</dt><dd class="font-semibold text-navy-900">{{ $whole($quote['gratuity']) }}</dd></div>
                            </dl>
                            <div class="mt-4 flex items-end justify-between border-t border-navy-200 pt-4">
                                <p class="font-bold text-navy-950">Total</p>
                                <p class="text-2xl font-extrabold text-navy-950" id="checkout-total">{{ $whole($quote['total']) }}</p>
                            </div>
                            <div class="mt-4 rounded-2xl bg-navy-950 p-4 text-white">
                                <div class="flex items-center justify-between"><span class="text-sm font-bold">Due today · paid in full</span><span class="text-xl font-extrabold text-brass-300" id="checkout-due">{{ $whole($quote['total']) }}</span></div>
                            </div>
                        </div>
                        <ul class="grid grid-cols-3 border-t border-navy-100 text-center text-[11px] font-semibold text-navy-500">
                            <li class="flex flex-col items-center gap-1 p-4"><i data-lucide="cloud-sun" class="h-5 w-5 text-brass-500"></i>Weather guarantee</li>
                            <li class="flex flex-col items-center gap-1 border-x border-navy-100 p-4"><i data-lucide="ship-wheel" class="h-5 w-5 text-brass-500"></i>Captain included</li>
                            <li class="flex flex-col items-center gap-1 p-4"><i data-lucide="shield-check" class="h-5 w-5 text-brass-500"></i>Secure payment</li>
                        </ul>
                    </div>
                </div>
            </aside>
        </form>
    </section>
@endsection
