@extends('layouts.app', [
    'title' => 'Secure checkout',
    'page' => 'checkout',
    'robots' => 'noindex', // one guest's booking (3.6)
    'headerSolid' => true,
    'mainClass' => 'pb-24 pt-28',
])

{{--
    Checkout of a held booking (PLAN 4.5, D22: paid in full, no deposit). The mockup's design. Prices come from
    QuoteService: the lines below are the booking's, and resources/js/pages/checkout.js asks the price endpoint
    when add-ons or the gratuity change. "Pay" saves the final choices and takes the card through Stripe's
    Payment Element (card data never touches our server) — or the fake gateway's test card, locally.
--}}
@php
    $tz = config('app.display_timezone');
    $boat = $booking->boat;
    $harbor = $boat->harbor;
    $cover = $yacht?->cover ?? App\Services\PublicCatalog::PLACEHOLDER;
    $start = $booking->starts_at->setTimezone($tz);
    $end = $booking->ends_at->setTimezone($tz);
    $exact = fn (int $cents) => money($cents);
    $cardField = 'w-full rounded-xl border border-navy-200 px-4 py-3.5 text-sm focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
    $step = 0;
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
            @if ($payable)
                <p class="inline-flex items-center gap-2 self-start rounded-full bg-brass-50 px-4 py-2 text-xs font-bold text-brass-800 ring-1 ring-brass-200 md:self-auto">
                    <i data-lucide="timer" class="h-4 w-4"></i>We're holding your time slot for <span id="hold-timer" class="tabular-nums">15:00</span>
                </p>
            @endif
        </div>
    </section>

    @unless ($payable)
        <section class="pt-10">
            <div class="container max-w-2xl">
                <div class="rounded-[2rem] bg-white p-8 text-center shadow-card ring-1 ring-navy-100 md:p-12">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-navy-50 text-navy-600"><i data-lucide="timer-off" class="h-7 w-7"></i></span>
                    <h1 class="mt-5 font-display text-3xl text-navy-950 md:text-4xl">This hold has ended</h1>
                    <p class="mt-3 text-navy-500">We kept {{ $start->format('D, M j') }} at {{ $start->format('g:i A') }} on {{ $boat->title }} for you, but the time ran out before the payment. Nothing was charged. Pick the time again — if it's still free, it's yours.</p>
                    <x-button :href="$editUrl" icon="arrow-right" class="mt-8">Back to {{ $boat->title }}</x-button>
                </div>
            </div>
        </section>
    @else
    <section class="pt-8">
        <form id="checkout-form" class="container grid gap-8 lg:grid-cols-[minmax(0,1fr)_420px]" novalidate data-checkout="{{ json_encode($checkoutData) }}">
            <div class="min-w-0 space-y-6">
                <div>
                    <h1 class="font-display text-4xl text-navy-950 md:text-5xl">Secure checkout</h1>
                    <p class="mt-2 text-navy-500">You're almost on the water. Review your details and pay to confirm.</p>
                </div>
                <p data-checkout-error hidden role="alert" class="flex items-center gap-3 rounded-2xl bg-red-50 px-5 py-4 text-sm font-semibold text-red-700 ring-1 ring-red-200"></p>

                {{-- Lead guest --}}
                <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                    <legend class="sr-only">Contact details</legend>
                    <div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-full bg-navy-950 text-xs font-extrabold text-white" aria-hidden="true">{{ ++$step }}</span><h2 class="text-xl font-extrabold text-navy-950">Lead guest details</h2></div>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <x-form.input name="first_name" label="First name" :value="$booking->contact_first_name" required maxlength="60" autocomplete="given-name" />
                        <x-form.input name="last_name" label="Last name" :value="$booking->contact_last_name" required maxlength="60" autocomplete="family-name" />
                        <x-form.input name="email" type="email" label="Email" :value="$booking->contact_email" required maxlength="254" autocomplete="email" placeholder="you@email.com" />
                        <x-form.input name="phone" type="tel" label="Mobile phone" :value="$booking->contact_phone" required maxlength="30" autocomplete="tel" placeholder="(312) 000-0000" />
                    </div>
                    <p class="mt-4 flex items-center gap-2 text-xs text-navy-500"><i data-lucide="message-circle" class="h-4 w-4 text-brass-500"></i>Your captain will text this number the day before your charter.</p>
                </fieldset>

                {{-- Trip details --}}
                <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                    <legend class="sr-only">Trip details</legend>
                    <div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-full bg-navy-950 text-xs font-extrabold text-white" aria-hidden="true">{{ ++$step }}</span><h2 class="text-xl font-extrabold text-navy-950">Trip details</h2></div>
                    <div class="mt-6 grid gap-5 sm:grid-cols-2">
                        <x-form.select name="occasion" label="Occasion" :options="array_combine($occasions, $occasions)" :value="$booking->occasion" placeholder="Select (optional)" />
                        <x-form.select name="heard" label="How did you hear about us?" :options="array_combine($heard, $heard)" :value="$booking->heard_from" placeholder="Select (optional)" />
                        <x-form.textarea name="notes" label="Notes for your captain" :value="$booking->customer_notes" rows="3" maxlength="1000" class="sm:col-span-2" placeholder="Dietary needs, surprises, accessibility…" />
                    </div>
                </fieldset>

                {{-- Add-ons: all optional (2026-11-25). The experience's suggested extras come first; nothing is charged unless ticked. --}}
                @if ($addons->isNotEmpty())
                    <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                        <legend class="sr-only">Add-ons (optional)</legend>
                        <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                            <div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-full bg-navy-950 text-xs font-extrabold text-white" aria-hidden="true">{{ ++$step }}</span><h2 class="text-xl font-extrabold text-navy-950">Make it yours</h2></div>
                            <p class="text-xs font-semibold text-navy-500">Optional · add as many as you like, or none</p>
                        </div>
                        <div class="mt-6 grid gap-3 md:grid-cols-2">
                            @foreach ($addons as $addon)
                                <label class="group relative flex cursor-pointer items-start gap-4 rounded-2xl bg-white p-4 text-left ring-2 ring-navy-100 transition hover:ring-navy-300 has-[:checked]:bg-brass-50 has-[:checked]:ring-brass-400 has-[:focus-visible]:ring-navy-950">
                                    <input type="checkbox" name="addons[]" value="{{ $addon->slug }}" class="sr-only" @checked(in_array($addon->slug, $chosenAddons, true))>
                                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-navy-50 text-navy-600 group-has-[:checked]:bg-brass-400 group-has-[:checked]:text-navy-950"><i data-lucide="{{ $addon->icon }}" class="h-5 w-5"></i></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-bold text-navy-950">{{ $addon->name }}</span>
                                        @if ($addon->suggested && $booking->experience)
                                            <span class="mt-1 inline-block rounded-md bg-brass-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-brass-800">Suggested for {{ $booking->experience->name }}</span>
                                        @endif
                                        <span class="mt-0.5 block text-xs text-navy-500">{{ $addon->description }}</span>
                                        <span class="mt-1.5 block text-sm font-extrabold text-navy-950">{{ money($addon->price_cents, 'never') }}<span class="text-[11px] font-semibold text-navy-400">{{ $addon->unit === 'per_guest' ? ' per guest' : ' per trip' }}</span></span>
                                    </span>
                                    <span class="grid h-6 w-6 shrink-0 place-items-center rounded-md text-transparent ring-2 ring-navy-200 group-has-[:checked]:bg-navy-950 group-has-[:checked]:text-white group-has-[:checked]:ring-navy-950"><i data-lucide="check" class="h-4 w-4"></i></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                {{-- Gratuity --}}
                <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                    <legend class="sr-only">Crew gratuity</legend>
                    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-full bg-navy-950 text-xs font-extrabold text-white" aria-hidden="true">{{ ++$step }}</span><h2 class="text-xl font-extrabold text-navy-950">Crew gratuity</h2></div>
                        <p class="text-xs font-semibold text-navy-500">Optional · 100% goes to your captain &amp; crew</p>
                    </div>
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        @foreach ($gratuityOptions as $pct)
                            <label class="group cursor-pointer rounded-2xl bg-white px-4 py-4 text-center ring-2 ring-navy-100 transition hover:ring-navy-300 has-[:checked]:bg-navy-950 has-[:checked]:text-white has-[:checked]:ring-navy-950 has-[:focus-visible]:ring-brass-400">
                                <input type="radio" name="gratuity" value="{{ $pct }}" class="sr-only" @checked($gratuity === $pct)>
                                <span class="block text-lg font-extrabold">{{ $pct === 0 ? 'No tip' : $pct.'%' }}</span>
                                <span class="block text-xs text-navy-400 group-has-[:checked]:text-brass-300">{{ $pct === 0 ? 'Tip in cash' : (isset($gratuityAmounts[$pct]) ? money($gratuityAmounts[$pct], 'never') : '') }}</span>
                                @if ($pct === $defaultGratuity)
                                    <span class="mt-1 block text-[10px] font-bold uppercase tracking-wider text-brass-600 group-has-[:checked]:text-white/60">Most chosen</span>
                                @endif
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                {{-- Payment --}}
                <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                    <legend class="sr-only">Payment</legend>
                    <div class="flex flex-col justify-between gap-2 sm:flex-row sm:items-center">
                        <div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-full bg-navy-950 text-xs font-extrabold text-white" aria-hidden="true">{{ ++$step }}</span><h2 class="text-xl font-extrabold text-navy-950">Payment</h2></div>
                        <p class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700"><i data-lucide="lock" class="h-3.5 w-3.5"></i>256-bit SSL encrypted</p>
                    </div>

                    <p class="mt-6 flex items-start gap-3 rounded-2xl bg-brass-50 p-4 text-sm text-navy-700 ring-1 ring-brass-200"><i data-lucide="circle-check" class="mt-0.5 h-5 w-5 shrink-0 text-brass-600"></i><span><strong class="text-navy-950">Paid in full today.</strong> Your yacht is booked as soon as the payment goes through. Nothing more is charged before your trip.</span></p>

                    @if ($checkoutData['provider'] === 'stripe')
                        {{-- Stripe's Payment Element: the card fields live in Stripe's own frames (PCI SAQ-A). --}}
                        <div id="payment-element" class="mt-6 min-h-24"></div>
                    @else
                        {{-- Fake gateway (local development and tests only): no real money. The inputs have no name, so the
                             number is never posted; only the brand and the last 4 digits are. --}}
                        <div class="mt-6 grid gap-5 sm:grid-cols-6" data-test-card>
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
                        <p class="mt-4 rounded-xl bg-navy-50 px-4 py-3 text-xs text-navy-500"><strong class="text-navy-800">Test mode:</strong> no real payment is taken. Use <button type="button" id="fill-demo" class="font-bold text-brass-700 underline">4242 4242 4242 4242</button> (or 4000 0000 0000 0002 to see a decline), any future expiry and any CVC.</p>
                    @endif
                </fieldset>

                {{-- Agreement + pay --}}
                <fieldset class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                    <legend class="sr-only">Agreements</legend>
                    <x-form.checkbox name="agree" required>I agree to the <a href="{{ route('legal.cancellation') }}" target="_blank" class="font-bold text-navy-950 underline">{{ $booking->policy_snapshot['name'] ?? 'Cancellation' }} cancellation &amp; weather policy</a> and the passenger safety waiver, and confirm all guests will follow the captain's instructions.<span class="text-red-600" aria-hidden="true"> *</span></x-form.checkbox>
                    <button type="submit" id="pay-btn" class="mt-8 inline-flex w-full items-center justify-center gap-3 rounded-full bg-brass-400 px-8 py-5 text-base font-extrabold text-navy-950 shadow-glow transition hover:-translate-y-0.5 hover:bg-brass-300 disabled:cursor-wait disabled:opacity-80">
                        <i data-lucide="lock" class="h-5 w-5"></i><span id="pay-label">Pay {{ $exact($booking->total_cents) }} &amp; confirm booking</span>
                    </button>
                    <p class="mt-4 text-center text-xs text-navy-400">You'll get a confirmation email with your booking details and the meeting point.</p>
                </fieldset>
            </div>

            {{-- Order summary --}}
            <aside class="order-first lg:order-none">
                <div class="lg:sticky lg:top-28 lg:-mx-4 lg:-mt-4 lg:max-h-[calc(100dvh-8rem)] lg:overflow-y-auto lg:px-4 lg:pt-4 lg:pb-4 no-scrollbar" data-sticky-panel>
                    <div class="overflow-hidden rounded-[2rem] bg-white shadow-card ring-1 ring-navy-100" aria-live="polite">
                        <div class="relative h-40 overflow-hidden">
                            <img src="{{ asset($cover) }}" alt="{{ $boat->title }}" class="h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-navy-950 via-navy-950/30 to-transparent"></div>
                            <div class="absolute bottom-4 left-5 right-5 flex items-end justify-between text-white">
                                <div><p class="text-[10px] font-bold uppercase tracking-[0.2em] text-brass-300">{{ $booking->experience?->name ?? 'Private charter' }}</p><p class="font-display text-2xl">{{ $boat->title }}</p></div>
                                <a href="{{ $editUrl }}" class="rounded-full bg-white/15 px-3 py-1.5 text-xs font-bold backdrop-blur hover:bg-white hover:text-navy-950">Edit<span class="sr-only"> booking</span></a>
                            </div>
                        </div>
                        <dl class="grid grid-cols-2 gap-px bg-navy-100 text-sm">
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Date</dt><dd class="mt-1 font-bold text-navy-950">{{ $start->format('D, M j, Y') }}</dd></div>
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Time</dt><dd class="mt-1 font-bold text-navy-950">{{ $start->format('g:i A') }} – {{ $end->format('g:i A') }}</dd></div>
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Guests</dt><dd class="mt-1 font-bold text-navy-950">{{ $booking->guests }} {{ Str::plural('guest', $booking->guests) }}</dd></div>
                            <div class="bg-white p-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Departs</dt><dd class="mt-1 font-bold text-navy-950">{{ $harbor?->name ?? 'Chicago' }}</dd></div>
                        </dl>

                        <div class="border-t border-navy-100 p-5">
                            <div id="promo-box">
                                <div @class(['flex gap-2', 'hidden' => $checkoutData['promo']]) data-promo-form>
                                    <label for="promo-input" class="sr-only">Promo code</label>
                                    <input id="promo-input" maxlength="30" autocomplete="off" placeholder="Promo code" class="min-w-0 flex-1 rounded-xl border border-navy-200 px-4 py-3 text-sm uppercase placeholder:normal-case focus:border-navy-950 focus:outline-hidden">
                                    <button type="button" data-apply-promo class="rounded-xl bg-navy-950 px-5 text-sm font-bold text-white hover:bg-navy-800">Apply</button>
                                </div>
                                <p data-promo-error hidden class="mt-2 text-xs font-semibold text-red-600" role="alert"></p>
                                <div @class(['items-center justify-between rounded-xl bg-emerald-50 px-4 py-3 text-sm ring-1 ring-emerald-200', 'flex' => $checkoutData['promo'], 'hidden' => ! $checkoutData['promo']]) data-promo-applied>
                                    <span class="flex items-center gap-2 font-bold text-emerald-800"><i data-lucide="tag" class="h-4 w-4"></i><span data-promo-label>{{ $checkoutData['promo'] ? $checkoutData['promo']['code'].' · '.$checkoutData['promo']['label'] : '' }}</span></span>
                                    <button type="button" data-remove-promo class="text-xs font-bold text-emerald-800 hover:underline">Remove</button>
                                </div>
                                <p data-promo-note hidden class="mt-2 text-xs font-semibold text-amber-700"></p>
                            </div>

                            <dl id="checkout-lines" class="mt-5 space-y-2.5 text-sm">
                                @foreach ($booking->items as $item)
                                    <div class="flex justify-between gap-4"><dt class="text-navy-600">{{ $item->label }}<span class="block text-[11px] text-navy-400">{{ $item->description }}</span></dt><dd @class(['font-semibold', 'text-emerald-700' => $item->amount_cents < 0, 'text-navy-900' => $item->amount_cents >= 0])>{{ $item->amount_cents < 0 ? '−'.$exact(-$item->amount_cents) : $exact($item->amount_cents) }}</dd></div>
                                @endforeach
                            </dl>
                            <div class="mt-4 flex items-end justify-between border-t border-navy-200 pt-4">
                                <p class="font-bold text-navy-950">Total</p>
                                <p class="text-2xl font-extrabold text-navy-950" id="checkout-total">{{ $exact($booking->total_cents) }}</p>
                            </div>
                            <div class="mt-4 rounded-2xl bg-navy-950 p-4 text-white">
                                <div class="flex items-center justify-between"><span class="text-sm font-bold">Due today · paid in full</span><span class="text-xl font-extrabold text-brass-300" id="checkout-due">{{ $exact($booking->total_cents) }}</span></div>
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
    @endunless
@endsection

@if ($payable && $checkoutData['provider'] === 'stripe')
    @push('head')
        {{-- Stripe.js must load from Stripe itself (PCI); allowed by the CSP with the Stripe driver. --}}
        <script src="https://js.stripe.com/v3/" nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}"></script>
    @endpush
@endif
