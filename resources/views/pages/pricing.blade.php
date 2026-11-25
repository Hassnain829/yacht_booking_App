@extends('layouts.app', [
    'title' => 'Pricing',
    'description' => 'Transparent Chicago yacht charter pricing: hourly yacht rates, captain included, free experiences, add-ons, fees and tax — all shown before you pay.',
    'page' => 'pricing',
])

@php
    $fieldClass = 'mt-1.5 w-full rounded-xl border border-navy-200 bg-white px-4 py-3 text-sm font-semibold focus:border-navy-950 focus:outline-hidden';
    $weekendPct = App\Support\Money::percent($pricing['weekend_multiplier_bp'] - 10000);
    $est = $estimator; // null when no boat is live (the estimator section is then left out)
    $estYacht = $est['yacht'] ?? null;
    $estQuote = $est['quote'] ?? null;
    $formula = [
        ['sailboat', 'Yacht rate', 'per hour × duration'],
        ['ship-wheel', 'Captain & crew', 'included in package'],
        ['sparkles', 'Experience', 'free · extras optional'],
        ['gift', 'Add-ons', 'optional'],
    ];
    // Data for the live estimator (resources/js/pages/pricing.js): choices and URLs only. Every price comes from
    // the quote endpoint (QuoteService); the browser never adds anything up.
    $estimatorData = [
        'yachts' => $yachts->map(fn ($y) => [
            'slug' => $y->slug, 'title' => $y->title, 'cover' => asset($y->cover), 'max_guests' => $y->max_guests, 'experiences' => $y->experiences,
            'lengths' => $y->lengths,
            'quoteUrl' => route('yachts.quote', $y->slug), 'bookUrl' => route('yachts.show', $y->slug),
        ])->values(),
        'experiences' => $experiences->mapWithKeys(fn ($e) => [$e->slug => $e->name]),
        'dates' => $est['dates'] ?? null,
    ];
    $policies = [
        ['credit-card', 'Paid in full at checkout', 'You pay the whole price when you book, and your yacht is confirmed at once. No deposit, and nothing more is charged before the trip.'],
        ['calendar-x', 'Cancellations', 'Each yacht has a Flexible, Moderate or Strict policy, shown before you pay. Cancel early enough and you get a full refund.'],
        ['cloud-lightning', 'Weather guarantee', 'If the captain cancels for weather, reschedule free or get a refund of 100% of what you paid.'],
        ['hand-coins', 'Crew gratuity', 'Gratuity is optional and goes 100% to your captain and crew. 18–20% is customary for great service.'],
    ];
@endphp

@section('content')
    <x-page-hero image="images/yacht-aerial.jpg" crumb="Pricing" image-class="opacity-50"
                 intro="No surprise fees at the dock. You see every line — yacht, captain, extras, fees and tax — before you pay a cent.">
        Honest pricing. <em>Captain included.</em>
    </x-page-hero>

    {{-- How the price is built --}}
    <section class="relative z-10 -mt-12">
        <div class="container">
            <div class="reveal grid gap-3 rounded-3xl bg-white p-4 shadow-card ring-1 ring-navy-100 sm:grid-cols-3 lg:grid-cols-[1fr_auto_1fr_auto_1fr_auto_1fr_auto_1fr] lg:items-center lg:p-6">
                @foreach ($formula as [$icon, $title, $text])
                    <div class="rounded-2xl bg-navy-50 p-4 text-center"><i data-lucide="{{ $icon }}" class="mx-auto h-6 w-6 text-brass-500"></i><p class="mt-2 text-sm font-bold text-navy-950">{{ $title }}</p><p class="text-xs text-navy-500">{{ $text }}</p></div>
                    <span class="hidden text-2xl font-light text-navy-300 lg:block" aria-hidden="true">{{ $loop->last ? '=' : '+' }}</span>
                @endforeach
                <div class="rounded-2xl bg-navy-950 p-4 text-center text-white sm:col-span-2 lg:col-span-1"><i data-lucide="badge-dollar-sign" class="mx-auto h-6 w-6 text-brass-300"></i><p class="mt-2 text-sm font-bold">Your all-in total</p><p class="text-xs text-white/60">+ fees &amp; tax, shown upfront</p></div>
            </div>
        </div>
    </section>

    {{-- Popular packages --}}
    <section class="py-24">
        <div class="container">
            <x-section-heading eyebrow="Popular packages" center class="reveal mx-auto max-w-2xl" subtitle="Here's what our most-booked charters actually cost, including captain, fees and tax.">
                Real examples, <em>real totals</em>
            </x-section-heading>
            <div class="mt-14 grid gap-8 lg:grid-cols-3">
                @foreach ($packages as $package)
                    @php($dark = $package->featured)
                    <article @class(['reveal relative flex flex-col overflow-hidden rounded-3xl', 'bg-navy-950 text-white shadow-2xl lg:-translate-y-4' => $dark, 'bg-white shadow-card ring-1 ring-navy-100' => ! $dark]) @if ($loop->index) data-delay="{{ $loop->index }}" @endif>
                        @if ($dark)<span class="absolute right-5 top-5 z-10 rounded-full bg-brass-400 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-navy-950">Most booked</span>@endif
                        <img src="{{ asset($package->yachtModel->cover) }}" alt="{{ $package->yachtModel->title }}" loading="lazy" class="h-48 w-full object-cover">
                        <div class="flex flex-1 flex-col p-7">
                            <p @class(['text-[11px] font-bold uppercase tracking-[0.2em]', 'text-brass-300' => $dark, 'text-brass-600' => ! $dark])>{{ $package->yachtModel->title }} · {{ $package->guests }} guests</p>
                            <h3 class="mt-2 font-display text-3xl">{{ $package->title }}</h3>
                            <p @class(['mt-4 font-display text-5xl', 'text-brass-300' => $dark, 'text-navy-950' => ! $dark])>{{ money($package->quote->totalCents, 'never') }}</p>
                            <p @class(['mt-1 text-xs', 'text-white/50' => $dark, 'text-navy-400' => ! $dark])>all-in incl. tax · {{ money((int) round($package->quote->totalCents / $package->guests), 'never') }} per guest</p>
                            <ul @class(['mt-6 space-y-2.5 text-sm', 'text-white/80' => $dark, 'text-navy-600' => ! $dark])>
                                @foreach ($package->perks as $perk)
                                    <li class="flex items-center gap-2.5"><i data-lucide="check" @class(['h-4 w-4', 'text-brass-300' => $dark, 'text-brass-500' => ! $dark])></i>{{ $perk }}</li>
                                @endforeach
                            </ul>
                            <div class="mt-auto pt-8">
                                <x-button :href="route('yachts.show', [$package->yacht, 'experience' => $package->experience, 'hours' => $package->hours, 'guests' => $package->guests, 'addons' => implode(',', $package->addons)]).'#book'"
                                          :variant="$dark ? 'primary' : 'dark'" size="block">Book this package</x-button>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Estimator (needs at least one live boat) --}}
    @if ($est)
    <section class="bg-white py-24">
        <div class="container grid gap-12 lg:grid-cols-[1fr_1.1fr] lg:gap-20">
            <div class="reveal">
                <x-section-heading eyebrow="Price estimator" subtitle="Play with the options to see exactly how your total changes. When it looks right, continue straight to booking with everything pre-filled.">
                    Build your quote in seconds
                </x-section-heading>
                <form id="estimator" class="mt-8 space-y-5" data-estimator="{{ json_encode($estimatorData) }}" novalidate>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <label class="block"><span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Yacht</span>
                            <select name="yacht" class="{{ $fieldClass }}">
                                @foreach ($yachts as $yacht)
                                    <option value="{{ $yacht->slug }}">{{ $yacht->title }} (up to {{ $yacht->max_guests }})</option>
                                @endforeach
                            </select></label>
                        <label class="block"><span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Experience</span>
                            <select name="experience" class="{{ $fieldClass }}">
                                <option value="">Just the boat</option>
                                @foreach ($est['experiences'] as $experience)
                                    <option value="{{ $experience->slug }}" @selected($loop->first)>{{ $experience->name }}</option>
                                @endforeach
                            </select></label>
                        <label class="block"><span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Duration</span>
                            <select name="hours" class="{{ $fieldClass }}">
                                @foreach ($est['durations'] as $hours)
                                    <option value="{{ $hours }}">{{ $hours }} {{ Str::plural('hour', $hours) }}</option>
                                @endforeach
                            </select></label>
                        <label class="block"><span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Guests</span>
                            <input name="guests" type="number" min="1" max="{{ $estYacht->max_guests }}" value="{{ $est['guests'] }}" inputmode="numeric" class="{{ $fieldClass }}"></label>
                    </div>
                    <fieldset>
                        <legend class="text-[11px] leading-6 font-bold uppercase tracking-[0.18em] text-navy-400">Day of week</legend>
                        <div class="mt-1.5 grid grid-cols-2 gap-2 rounded-xl bg-navy-50 p-1">
                            <label class="cursor-pointer"><input type="radio" name="day" value="weekday" class="peer sr-only" checked><span class="block rounded-lg py-2.5 text-center text-sm font-bold text-navy-500 peer-checked:bg-white peer-checked:text-navy-950 peer-checked:shadow peer-focus-visible:ring-2 peer-focus-visible:ring-brass-400">Mon – Thu</span></label>
                            <label class="cursor-pointer"><input type="radio" name="day" value="weekend" class="peer sr-only"><span class="block rounded-lg py-2.5 text-center text-sm font-bold text-navy-500 peer-checked:bg-white peer-checked:text-navy-950 peer-checked:shadow peer-focus-visible:ring-2 peer-focus-visible:ring-brass-400">Fri – Sun (+{{ $weekendPct }})</span></label>
                        </div>
                    </fieldset>
                    <div>
                        <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Add-ons</span>
                        <div id="est-addons" class="mt-2 flex flex-wrap gap-2">
                            @foreach ($addons as $addon)
                                <button type="button" data-addon="{{ $addon->slug }}" aria-pressed="false" class="inline-flex items-center gap-2 rounded-full border border-navy-200 px-4 py-2 text-xs font-bold text-navy-700 transition hover:border-navy-950">
                                    <i data-lucide="{{ $addon->icon }}" class="h-3.5 w-3.5"></i>{{ $addon->name }} · {{ money($addon->price_cents) }}{{ $addon->unit === 'per_guest' ? '/guest' : '' }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </form>
            </div>
            <div class="reveal" data-delay="1">
                <div class="sticky top-28 overflow-hidden rounded-3xl bg-navy-950 text-white shadow-2xl lg:max-h-[calc(100dvh-8rem)] lg:overflow-y-auto no-scrollbar" data-sticky-panel aria-live="polite">
                    <div class="relative h-44 overflow-hidden">
                        <img id="est-image" src="{{ asset($estYacht->cover) }}" alt="" class="h-full w-full object-cover opacity-70">
                        <div class="absolute inset-0 bg-gradient-to-t from-navy-950 to-transparent"></div>
                        <div class="absolute bottom-4 left-7">
                            <p id="est-experience" class="text-[11px] font-bold uppercase tracking-[0.2em] text-brass-300">{{ $est['experiences']->first()?->name }}</p>
                            <p id="est-title" class="font-display text-2xl">{{ $estYacht->title }} · {{ $estQuote->hours }} hrs · {{ $estQuote->guests }} guests</p>
                        </div>
                    </div>
                    <div class="p-7">
                        <dl id="est-lines" class="space-y-3 text-sm">
                            @foreach ($estQuote->lines as $line)
                                <div class="flex justify-between gap-4"><dt class="text-white/70">{{ $line->label }}<span class="block text-xs text-white/40">{{ $line->description }}</span></dt><dd class="font-semibold">{{ money($line->amountCents, 'never') }}</dd></div>
                            @endforeach
                            <div class="flex justify-between gap-4"><dt class="text-white/70">Taxes ({{ App\Support\Money::percent($pricing['tax_rate_bp']) }})</dt><dd class="font-semibold">{{ money($estQuote->taxCents, 'never') }}</dd></div>
                        </dl>
                        <div class="mt-5 flex items-end justify-between border-t border-white/10 pt-5">
                            <div><p class="text-xs font-bold uppercase tracking-[0.18em] text-white/50">Estimated total</p><p class="text-[11px] text-white/40">Gratuity optional, added at checkout</p></div>
                            <p id="est-total" class="font-display text-4xl text-brass-300">{{ money($estQuote->totalCents, 'never') }}</p>
                        </div>
                        <p class="mt-4 rounded-2xl bg-white/5 p-4 text-sm text-white/60">Paid in full at checkout. Pick your exact date and start time on the yacht's page.</p>
                        <a id="est-book" href="{{ route('yachts.show', $estYacht->slug) }}#book" class="mt-6 flex w-full items-center justify-center gap-2 rounded-full bg-brass-400 py-4 text-sm font-bold text-navy-950 hover:bg-brass-300">Continue to booking <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @endif

    {{-- Rate card --}}
    <section class="py-24">
        <div class="container">
            <div class="reveal flex flex-col justify-between gap-4 md:flex-row md:items-end">
                <x-section-heading eyebrow="Rate card" :tight="false">Yacht rates</x-section-heading>
                <p class="text-sm text-navy-500">All rates in USD. Fuel for standard routes included.</p>
            </div>
            <div class="reveal no-scrollbar relative mt-10 overflow-x-auto rounded-3xl bg-white shadow-card ring-1 ring-navy-100">
                <table class="w-full min-w-[860px] text-left text-sm">
                    <caption class="sr-only">Hourly rates, captain fees and starting prices for every yacht</caption>
                    <thead class="bg-navy-950 text-[11px] font-bold uppercase tracking-[0.18em] text-white/70">
                        <tr>
                            <th scope="col" class="px-6 py-5">Yacht</th><th scope="col" class="px-4 py-5">Guests</th><th scope="col" class="px-4 py-5">Mon–Thu /hr</th><th scope="col" class="px-4 py-5">Fri–Sun /hr</th>
                            <th scope="col" class="px-4 py-5">Captain &amp; crew /hr</th><th scope="col" class="px-4 py-5">Min. hours</th><th scope="col" class="px-4 py-5">Starting at*</th><th scope="col" class="px-6 py-5"><span class="sr-only">Book</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-navy-100">
                        @foreach ($yachts as $yacht)
                            <tr class="transition hover:bg-navy-50/60">
                                <th scope="row" class="px-6 py-4 font-normal"><a href="{{ route('yachts.show', $yacht->slug) }}" class="flex items-center gap-4"><img src="{{ asset($yacht->cover) }}" alt="" loading="lazy" class="h-12 w-16 rounded-xl object-cover"><span><span class="block font-display text-lg text-navy-950">{{ $yacht->title }}</span><span class="text-xs text-navy-400">{{ $yacht->length_ft }} ft {{ $yacht->category }}</span></span></a></th>
                                <td class="px-4 py-4 font-semibold">{{ $yacht->max_guests }}</td>
                                <td class="px-4 py-4 font-bold text-navy-950">{{ money($yacht->hourly_rate_cents) }}</td>
                                <td class="px-4 py-4 font-bold text-navy-950">{{ money((int) round($yacht->hourly_rate_cents * $pricing['weekend_multiplier_bp'] / 10000 / 100) * 100) }}</td>
                                <td class="px-4 py-4 text-navy-600">{{ money($yacht->captain_rate_cents) }} <span class="text-xs text-navy-400">({{ $yacht->crew_count > 1 ? 'captain + '.($yacht->crew_count - 1).' crew' : 'captain' }})</span></td>
                                <td class="px-4 py-4 text-navy-600">{{ $yacht->min_hours }} hrs</td>
                                <td class="px-4 py-4 font-extrabold text-brass-700">{{ $startingAt[$yacht->slug] !== null ? money($startingAt[$yacht->slug]) : '—' }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if ($yacht->booking_enabled)
                                        <a href="{{ route('yachts.show', $yacht->slug) }}#book" class="inline-flex rounded-full bg-navy-950 px-4 py-2 text-xs font-bold text-white hover:bg-brass-400 hover:text-navy-950">Book<span class="sr-only"> {{ $yacht->title }}</span></a>
                                    @else
                                        <span class="inline-flex rounded-full bg-navy-100 px-4 py-2 text-xs font-bold text-navy-400">Unavailable</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-4 text-xs text-navy-400">*Starting at = the shortest trip on a weekday: charter, captain and fuel &amp; cleaning, before tax, add-ons and optional gratuity.</p>
        </div>
    </section>

    {{-- Add-ons --}}
    <section class="bg-white py-24">
        <div class="container">
            <x-section-heading eyebrow="Extras" center :tight="false" class="reveal mx-auto max-w-2xl">Add-ons &amp; upgrades</x-section-heading>
            <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($addons as $addon)
                    <div class="reveal rounded-3xl border border-navy-100 p-6 transition hover:-translate-y-1 hover:shadow-card" @if ($loop->index % 4) data-delay="{{ $loop->index % 4 }}" @endif>
                        <span class="grid h-12 w-12 place-items-center rounded-2xl bg-brass-100 text-brass-700"><i data-lucide="{{ $addon->icon }}" class="h-6 w-6"></i></span>
                        <h3 class="mt-5 text-lg font-bold text-navy-950">{{ $addon->name }}</h3>
                        <p class="mt-1 text-sm text-navy-500">{{ $addon->description }}</p>
                        <p class="mt-4 text-xl font-extrabold text-navy-950">{{ money($addon->price_cents) }}<span class="text-sm font-semibold text-navy-400">{{ $addon->unit === 'per_guest' ? ' / guest' : ' flat' }}</span></p>
                    </div>
                @endforeach
                <div class="reveal flex flex-col justify-center rounded-3xl bg-navy-950 p-6 text-white" data-delay="3">
                    <p class="font-display text-2xl">Need something else?</p>
                    <p class="mt-2 text-sm text-white/60">Florists, cakes, live music, branded décor — just ask.</p>
                    <a href="{{ route('contact') }}" class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-brass-300">Contact our team <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                </div>
            </div>
        </div>
    </section>

    {{-- Policies --}}
    <section id="policies" class="scroll-mt-28 py-24">
        <div class="container grid gap-12 lg:grid-cols-[1fr_1.5fr]">
            <div class="reveal">
                <x-section-heading eyebrow="Policies" subtitle="Clear rules, written in plain English. Full details are in our cancellation policy.">
                    Payments, cancellations &amp; weather
                </x-section-heading>
                <a href="{{ route('legal.cancellation') }}" class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-brass-600 hover:text-brass-700">Read the cancellation policy <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
            </div>
            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ($policies as [$icon, $title, $text])
                    <div class="reveal rounded-3xl bg-white p-7 ring-1 ring-navy-100" @if ($loop->index) data-delay="{{ $loop->index }}" @endif>
                        <i data-lucide="{{ $icon }}" class="h-7 w-7 text-brass-500"></i>
                        <h3 class="mt-4 text-lg font-bold text-navy-950">{{ $title }}</h3>
                        <p class="mt-2 text-sm leading-relaxed text-navy-500">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
