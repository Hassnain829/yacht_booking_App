@extends('layouts.app', [
    'title' => $yacht->title.' — '.$yacht->length_ft.' ft '.$yacht->category,
    'description' => $yacht->short,
    'page' => 'yacht',
    'ogImage' => $yacht->cover,
    'mobileBar' => true,
])

@php
    $business = config('site.business');
    $harbor = $yacht->harbor;
    $available = (bool) $yacht->booking_enabled;
    $gallery = $yacht->gallery;
    $weekendRate = (int) round($yacht->hourly_rate_cents * $pricing['weekend_multiplier_bp'] / 10000 / 100) * 100;
    $captainLabel = $yacht->crew_count > 1 ? 'Captain & crew' : 'Captain';
    $firstExperience = $experiences->first();
    $fieldClass = 'mt-1.5 w-full rounded-xl border border-navy-200 bg-white text-sm font-semibold focus:border-navy-950 focus:outline-hidden';
    $specs = [
        ['ruler', 'Length', $yacht->length_ft.' ft'],
        ['users', 'Guests', 'Up to '.$yacht->max_guests],
        ['clock', 'Minimum', $yacht->min_hours.' hours'],
        ['bed-double', 'Cabins', $yacht->staterooms ?: '—'],
        ['bath', 'Restrooms', $yacht->heads],
        ['calendar-check', 'Year', $yacht->year],
    ];
    // Data for the live estimate (resources/js/pages/yacht.js). Prices only DISPLAYED in the browser.
    $quoteData = [
        'yacht' => collect($yacht->toArray())->only(['slug', 'min_hours', 'max_guests', 'hourly_rate_cents', 'captain_rate_cents', 'crew_count', 'cleaning_fee_cents']),
        'experiences' => $experiences->map(fn ($e) => ['slug' => $e->slug, 'name' => $e->name, 'package_fee_cents' => $e->package_fee_cents, 'durations' => $e->durations])->values(),
        'pricing' => collect($pricing)->only(['weekend_multiplier_bp', 'tax_rate_bp', 'deposit_bp']),
    ];
@endphp

@section('content')
    {{-- ============================== HEAD + GALLERY ============================== --}}
    <section class="bg-navy-950 pb-10 pt-36 text-white md:pt-40">
        <div class="container">
            <nav class="flex flex-wrap gap-2 text-[11px] font-bold uppercase tracking-[0.25em] text-white/50" aria-label="Breadcrumb">
                <a href="{{ route('home') }}" class="hover:text-white">Home</a><span aria-hidden="true">/</span>
                <a href="{{ route('yachts.index') }}" class="hover:text-white">Yachts</a><span aria-hidden="true">/</span>
                <span class="text-brass-300" aria-current="page">{{ $yacht->title }}</span>
            </nav>

            <div class="mt-5 flex flex-col justify-between gap-6 md:flex-row md:items-end">
                <div>
                    @if ($available)
                        @if ($yacht->badge)<span class="inline-flex rounded-full bg-brass-400 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-navy-950">{{ $yacht->badge }}</span>@endif
                    @else
                        <x-badge variant="unavailable" icon="calendar-x">Unavailable</x-badge>
                    @endif
                    <h1 class="mt-4 font-display text-5xl md:text-6xl">{{ $yacht->title }}</h1>
                    <div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-white/70">
                        @if ($yacht->reviews_count > 0 && $yacht->rating_avg)
                            <span class="inline-flex items-center gap-1.5"><x-stars :rating="$yacht->rating_avg" /><strong class="ml-1 text-white">{{ number_format($yacht->rating_avg, 1) }}</strong> ({{ $yacht->reviews_count }} reviews)</span>
                        @else
                            <span class="inline-flex items-center gap-1.5"><i data-lucide="sparkles" class="h-4 w-4 text-brass-300"></i>New on Chicago Yacht Booking</span>
                        @endif
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="sailboat" class="h-4 w-4 text-brass-300"></i>{{ $yacht->length_ft }} ft {{ $yacht->category }}</span>
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="users" class="h-4 w-4 text-brass-300"></i>Up to {{ $yacht->max_guests }} guests</span>
                        <span class="inline-flex items-center gap-1.5"><i data-lucide="map-pin" class="h-4 w-4 text-brass-300"></i>{{ $harbor?->name }}</span>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="button" id="share-btn" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-5 py-2.5 text-sm font-bold hover:bg-white hover:text-navy-950"><i data-lucide="share-2" class="h-4 w-4"></i>Share</button>
                    <button type="button" id="save-btn" data-slug="{{ $yacht->slug }}" data-name="{{ $yacht->title }}" aria-pressed="false" class="inline-flex items-center gap-2 rounded-full border border-white/20 px-5 py-2.5 text-sm font-bold hover:bg-white hover:text-navy-950"><i data-lucide="heart" class="h-4 w-4"></i>Save</button>
                </div>
            </div>

            <div class="mt-8" data-lightbox-gallery>
                <div class="relative grid h-[340px] grid-cols-4 grid-rows-2 gap-3 overflow-hidden rounded-3xl md:h-[520px]">
                    @foreach (array_slice($gallery, 0, 5) as $i => $photo)
                        <button type="button" data-lightbox="{{ asset($photo) }}" aria-label="Open photo {{ $i + 1 }} of {{ count($gallery) }}"
                                @class([
                                    'group relative overflow-hidden',
                                    'col-span-4 row-span-2 md:col-span-2' => $i === 0,
                                    'hidden md:block' => $i > 0,
                                    'md:row-span-2' => count($gallery) < 5 && $i === 1,
                                ])>
                            <img src="{{ asset($photo) }}" alt="{{ $yacht->title }} photo {{ $i + 1 }}" @if ($i > 0) loading="lazy" @else fetchpriority="high" @endif class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
                        </button>
                    @endforeach
                    @foreach (array_slice($gallery, 5) as $photo)
                        <button type="button" data-lightbox="{{ asset($photo) }}" class="hidden" tabindex="-1" aria-hidden="true"></button>
                    @endforeach
                    <button type="button" data-open-gallery class="absolute bottom-4 right-4 inline-flex items-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-bold text-navy-950 shadow-lg hover:bg-brass-300"><i data-lucide="images" class="h-4 w-4"></i>View all {{ count($gallery) }} photos</button>
                </div>
            </div>
        </div>
    </section>

    {{-- ============================== DETAILS + BOOKING CARD ============================== --}}
    <section class="py-14 md:py-20">
        <div class="container grid gap-12 lg:grid-cols-[minmax(0,1fr)_400px] lg:gap-16">
            <div class="min-w-0 space-y-14">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($specs as [$icon, $label, $value])
                        <div class="rounded-2xl border border-navy-100 bg-white p-5">
                            <i data-lucide="{{ $icon }}" class="h-5 w-5 text-brass-500"></i>
                            <p class="mt-3 text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">{{ $label }}</p>
                            <p class="mt-1 text-lg font-extrabold text-navy-950">{{ $value }}</p>
                        </div>
                    @endforeach
                </div>

                <div>
                    <h2 class="font-display text-3xl text-navy-950">About {{ $yacht->title }}</h2>
                    <p class="mt-4 text-lg leading-relaxed text-navy-600">{{ $yacht->description }}</p>
                    <div class="mt-6 flex items-start gap-4 rounded-2xl bg-brass-50 p-5 ring-1 ring-brass-200">
                        <i data-lucide="ship-wheel" class="mt-0.5 h-6 w-6 shrink-0 text-brass-600"></i>
                        <p class="text-sm leading-relaxed text-navy-700"><strong class="text-navy-950">{{ $yacht->crew_count > 1 ? 'Captain + '.($yacht->crew_count - 1).' crew included.' : 'Licensed captain included.' }}</strong> Your USCG-licensed captain handles navigation, safety and docking — you just enjoy the ride.</p>
                    </div>
                </div>

                <div>
                    <h2 class="font-display text-3xl text-navy-950">Amenities</h2>
                    <ul class="mt-6 grid gap-3 sm:grid-cols-2">
                        @foreach ($yacht->amenities as $amenity)
                            <li class="flex items-center gap-3 rounded-xl bg-white px-4 py-3 text-sm font-semibold text-navy-700 ring-1 ring-navy-100"><i data-lucide="circle-check" class="h-5 w-5 text-brass-500"></i>{{ $amenity }}</li>
                        @endforeach
                    </ul>
                </div>

                <div>
                    <h2 class="font-display text-3xl text-navy-950">Experiences on this yacht</h2>
                    <p class="mt-2 text-navy-500">Choose an experience at booking — each one sets the route, timing and extras.</p>
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        @foreach ($experiences as $experience)
                            {{-- Unavailable boats never link to booking: the card opens the experience details instead. --}}
                            <a href="{{ $available ? route('booking.create', ['yacht' => $yacht->slug, 'experience' => $experience->slug]) : route('experiences.index').'#'.$experience->slug }}" class="group flex items-center gap-4 rounded-2xl bg-white p-3 pr-5 ring-1 ring-navy-100 transition hover:ring-brass-400">
                                <img src="{{ asset($experience->image) }}" alt="" loading="lazy" class="h-20 w-20 shrink-0 rounded-xl object-cover">
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-navy-950 group-hover:text-brass-600">{{ $experience->name }}</p>
                                    <p class="mt-0.5 text-xs text-navy-500">{{ implode(', ', $experience->offered_durations) }} hrs · {{ $experience->allowed_weekdays ? 'Wed & Sat only' : 'Daily' }}</p>
                                    <p class="mt-1 text-xs font-bold text-brass-600">{{ $experience->package_fee_cents ? '+'.money($experience->package_fee_cents).' package' : 'No package fee' }}</p>
                                </div>
                                <i data-lucide="arrow-right" class="h-4 w-4 text-navy-300 transition group-hover:translate-x-1 group-hover:text-brass-500"></i>
                            </a>
                        @endforeach
                    </div>
                </div>

                <div>
                    <h2 class="font-display text-3xl text-navy-950">Pricing</h2>
                    <div class="mt-6 overflow-hidden rounded-3xl bg-white ring-1 ring-navy-100">
                        <div class="grid divide-y divide-navy-100 sm:grid-cols-3 sm:divide-x sm:divide-y-0">
                            <div class="p-6"><p class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Mon – Thu</p><p class="mt-2 text-3xl font-extrabold text-navy-950">{{ money($yacht->hourly_rate_cents) }}<span class="text-sm font-semibold text-navy-400">/hr</span></p></div>
                            <div class="p-6"><p class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Fri – Sun</p><p class="mt-2 text-3xl font-extrabold text-navy-950">{{ money($weekendRate) }}<span class="text-sm font-semibold text-navy-400">/hr</span></p></div>
                            <div class="p-6"><p class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">{{ $yacht->crew_count > 1 ? 'Captain & crew' : 'Captain' }}</p><p class="mt-2 text-3xl font-extrabold text-navy-950">{{ money($yacht->captain_rate_cents) }}<span class="text-sm font-semibold text-navy-400">/hr</span></p></div>
                        </div>
                        <div class="border-t border-navy-100 bg-navy-50/60 p-6">
                            <p class="text-sm font-bold text-navy-950">Example: {{ $yacht->min_hours }}-hour weekday private charter, {{ $sample['guests'] }} guests</p>
                            <dl class="mt-4 space-y-2 text-sm">
                                @foreach ($sample['lines'] as $line)
                                    <div class="flex justify-between gap-4"><dt class="text-navy-600">{{ $line['label'] }} <span class="text-navy-400">· {{ $line['sub'] }}</span></dt><dd class="font-semibold text-navy-900">{{ money($line['amount'], 'never') }}</dd></div>
                                @endforeach
                                <div class="flex justify-between gap-4"><dt class="text-navy-600">Taxes (est. {{ App\Support\Money::percent($pricing['tax_rate_bp']) }})</dt><dd class="font-semibold text-navy-900">{{ money($sample['tax'], 'never') }}</dd></div>
                                <div class="flex justify-between gap-4 border-t border-navy-200 pt-3 text-base"><dt class="font-bold text-navy-950">Estimated total</dt><dd class="font-extrabold text-navy-950">{{ money($sample['total'], 'never') }}</dd></div>
                            </dl>
                            <p class="mt-3 text-xs text-navy-400">Gratuity optional, added at checkout. {{ App\Support\Money::percent($pricing['deposit_bp']) }} deposit secures your date.</p>
                        </div>
                    </div>
                </div>

                <div class="grid gap-6 sm:grid-cols-2">
                    <div class="rounded-3xl bg-white p-6 ring-1 ring-navy-100">
                        <h3 class="flex items-center gap-2 text-lg font-bold text-navy-950"><i data-lucide="circle-check" class="h-5 w-5 text-emerald-600"></i>Included</h3>
                        <ul class="mt-4 space-y-2.5 text-sm text-navy-600">
                            <li>Licensed USCG captain{{ $yacht->crew_count > 1 ? ' & crew' : '' }}</li><li>Fuel for standard routes</li><li>Life jackets &amp; safety gear</li><li>Ice, water &amp; cups</li><li>Bluetooth sound system</li>
                        </ul>
                    </div>
                    <div class="rounded-3xl bg-white p-6 ring-1 ring-navy-100">
                        <h3 class="flex items-center gap-2 text-lg font-bold text-navy-950"><i data-lucide="info" class="h-5 w-5 text-brass-500"></i>Good to know</h3>
                        <ul class="mt-4 space-y-2.5 text-sm text-navy-600">
                            <li>BYOB welcome (21+), no glass</li><li>Arrive 20 minutes before departure</li><li>Soft-soled shoes on deck</li><li>Free weather rescheduling</li><li>Crew gratuity not included</li>
                        </ul>
                    </div>
                </div>

                @if ($harbor)
                    <div>
                        <h2 class="font-display text-3xl text-navy-950">Departure location</h2>
                        <p class="mt-2 text-navy-500"><strong class="text-navy-800">{{ $harbor->name }}</strong> · {{ $harbor->area }}. {{ $harbor->notes }}</p>
                        <div class="mt-6 overflow-hidden rounded-3xl ring-1 ring-navy-100">
                            <iframe title="Map of {{ $harbor->name }}" src="https://www.google.com/maps?q={{ urlencode($harbor->map_query) }}&amp;output=embed" class="h-72 w-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Booking card --}}
            <aside>
                <div class="lg:sticky lg:top-28">
                    @if ($available)
                        <form id="y-book-form" action="{{ route('booking.create') }}" method="get" data-quote="{{ json_encode($quoteData) }}" class="overflow-hidden rounded-3xl bg-white shadow-card ring-1 ring-navy-100">
                            <input type="hidden" name="yacht" value="{{ $yacht->slug }}">
                            <div class="bg-navy-950 p-6 text-white">
                                <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-brass-300">Starting from</p>
                                <p class="mt-1 text-4xl font-extrabold">{{ money($yacht->hourly_rate_cents) }}<span class="text-base font-semibold text-white/50">/hr</span></p>
                                <p class="mt-1 text-xs text-white/60">{{ $yacht->min_hours }}-hour minimum · captain fee {{ money($yacht->captain_rate_cents) }}/hr</p>
                            </div>
                            <div class="space-y-4 p-6">
                                <label class="block">
                                    <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Experience</span>
                                    <select name="experience" class="{{ $fieldClass }} px-4 py-3">
                                        @foreach ($experiences as $experience)
                                            <option value="{{ $experience->slug }}">{{ $experience->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="block">
                                        <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Date</span>
                                        <input type="date" name="date" min="{{ $today }}" class="{{ $fieldClass }} px-3 py-3">
                                    </label>
                                    <label class="block">
                                        <span class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Duration</span>
                                        <select name="hours" class="{{ $fieldClass }} px-3 py-3">
                                            @foreach ($firstExperience?->offered_durations ?? [$yacht->min_hours] as $hours)
                                                <option value="{{ $hours }}">{{ $hours }} hours</option>
                                            @endforeach
                                        </select>
                                    </label>
                                </div>
                                <div>
                                    <span id="guests-label" class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Guests</span>
                                    <div class="mt-1.5 flex items-center justify-between rounded-xl border border-navy-200 px-2 py-1.5">
                                        <button type="button" data-guests-step="-1" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-navy-50" aria-label="Fewer guests"><i data-lucide="minus" class="h-4 w-4"></i></button>
                                        <input name="guests" type="number" min="1" max="{{ $yacht->max_guests }}" value="{{ $defaultGuests }}" inputmode="numeric" class="w-16 text-center text-sm font-bold focus:outline-hidden" aria-labelledby="guests-label">
                                        <button type="button" data-guests-step="1" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-navy-50" aria-label="More guests"><i data-lucide="plus" class="h-4 w-4"></i></button>
                                    </div>
                                    <p class="mt-1 text-xs text-navy-400">Maximum {{ $yacht->max_guests }} guests (USCG limit)</p>
                                </div>
                                <div id="y-estimate" class="rounded-2xl bg-navy-50 p-4 text-sm" aria-live="polite">
                                    <div class="flex justify-between"><span class="text-navy-500">Charter + captain</span><span class="font-semibold" data-est="base">{{ money($estimate['charter'] + $estimate['captain'], 'never') }}</span></div>
                                    <div class="mt-1 flex justify-between"><span class="text-navy-500">Package, fees &amp; tax</span><span class="font-semibold" data-est="extras">{{ money($estimate['total'] - $estimate['charter'] - $estimate['captain'], 'never') }}</span></div>
                                    <div class="mt-2 flex justify-between border-t border-navy-200 pt-2"><span class="font-bold text-navy-950">Estimated total</span><span class="font-extrabold text-navy-950" data-est="total">{{ money($estimate['total'], 'never') }}</span></div>
                                    <p class="mt-2 hidden text-xs font-semibold text-brass-700" data-est="weekend">Weekend rate applied</p>
                                </div>
                                <x-button type="submit" icon="arrow-right" size="block">Check Availability</x-button>
                                <p class="flex items-center justify-center gap-2 text-xs text-navy-400"><i data-lucide="lock" class="h-3.5 w-3.5"></i>No charge until checkout</p>
                            </div>
                        </form>
                    @else
                        <div class="overflow-hidden rounded-3xl bg-white shadow-card ring-1 ring-navy-100">
                            <div class="bg-navy-950 p-6 text-white">
                                <x-badge variant="unavailable" icon="calendar-x">Unavailable</x-badge>
                                <p class="mt-4 font-display text-2xl">Not taking bookings right now</p>
                                <p class="mt-2 text-sm text-white/60">{{ $yacht->title }} is temporarily unavailable. Our crew can suggest a similar yacht for your date.</p>
                            </div>
                            <div class="space-y-3 p-6">
                                <x-button :href="route('yachts.index')" icon="arrow-right" class="w-full">See available yachts</x-button>
                                <x-button :href="route('contact')" variant="outline" class="w-full">Ask our crew</x-button>
                            </div>
                        </div>
                    @endif
                    <div class="mt-4 flex items-center gap-4 rounded-2xl bg-white p-4 ring-1 ring-navy-100">
                        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brass-100 text-brass-700"><i data-lucide="phone" class="h-5 w-5"></i></span>
                        <div class="text-sm"><p class="font-bold text-navy-950">Questions about {{ $yacht->title }}?</p><a href="tel:{{ $business['phone_href'] }}" class="text-brass-600 hover:underline">{{ $business['phone'] }}</a></div>
                    </div>
                </div>
            </aside>
        </div>
    </section>

    {{-- ============================== SIMILAR ============================== --}}
    <section class="border-t border-navy-100 bg-white py-20">
        <div class="container">
            <div class="flex items-end justify-between gap-6">
                <h2 class="font-display text-4xl text-navy-950">You may also like</h2>
                <a href="{{ route('yachts.index') }}" class="hidden items-center gap-2 text-sm font-bold text-brass-600 hover:text-brass-700 sm:inline-flex">All yachts <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
            </div>
            <div class="mt-10 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($similar as $other)
                    <x-yacht-card :yacht="$other" :delay="$loop->index" />
                @endforeach
            </div>
        </div>
    </section>

    {{-- Mobile sticky booking bar (rendered after the footer, outside <main>) --}}
    @push('modals')
    <div id="mobile-book-bar" class="fixed inset-x-0 bottom-0 z-40 border-t border-navy-100 bg-white/95 p-4 backdrop-blur lg:hidden">
        <div class="flex items-center justify-between gap-4">
            @if ($available)
                <div><p class="text-xs text-navy-400">Est. total · <span data-bar="hours">{{ $estimate['hours'] }}</span> hrs</p><p class="text-xl font-extrabold text-navy-950" data-bar="total">{{ money($estimate['total'], 'never') }}</p></div>
                <a href="{{ route('booking.create', ['yacht' => $yacht->slug]) }}" data-bar="link" class="inline-flex items-center justify-center gap-2 rounded-full bg-brass-400 px-7 py-3 text-sm font-bold text-navy-950 shadow-glow transition hover:bg-brass-300">Book Now</a>
            @else
                <div><p class="text-xs text-navy-400">{{ $yacht->title }}</p><p class="text-base font-extrabold text-navy-950">Currently unavailable</p></div>
                <a href="{{ route('yachts.index') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-navy-950 px-6 py-3 text-sm font-bold text-white">See yachts</a>
            @endif
        </div>
    </div>
    @endpush
@endsection
