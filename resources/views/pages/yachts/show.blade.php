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
    $policy = $yacht->policy;
    $lengths = $yacht->lengths;
    $lengthsLabel = count($lengths) > 1 ? $lengths[0].' – '.last($lengths).' hours' : $lengths[0].' '.Str::plural('hour', $lengths[0]);
    $specs = [
        ['ruler', 'Length', $yacht->length_ft.' ft'],
        ['users', 'Guests', 'Up to '.$yacht->max_guests],
        ['clock', 'Trip length', $lengthsLabel],
        ['bed-double', 'Cabins', $yacht->staterooms ?: '—'],
        ['bath', 'Restrooms', $yacht->heads],
        ['calendar-check', 'Year', $yacht->year],
    ];
    // Quick facts under the title (Boatsetter-style, our design): what the guest should know at a glance.
    $facts = array_values(array_filter([
        ['ship-wheel', $yacht->crew_count > 1 ? 'Captain & '.($yacht->crew_count - 1).' crew included' : 'Captain included'],
        ['zap', 'Instant confirmation'],
        $policy ? ['calendar-x', $policy['name'].' cancellation'] : null,
        $harbor ? ['map-pin', 'Departs '.$harbor->name] : null,
    ]));
    // The booking card's data (resources/js/pages/yacht.js): choices and URLs only. Every price comes from the
    // quote endpoint (QuoteService); the browser never adds anything up. An experience brings its departure window
    // (`times`), days and season, so the card can tell the guest when a chosen date or time doesn't fit it.
    $card = [
        'urls' => ['availability' => route('yachts.availability', $yacht->slug), 'quote' => route('yachts.quote', $yacht->slug)],
        'maxGuests' => (int) $yacht->max_guests,
        'lengths' => $lengths,
        'experiences' => $experiences->mapWithKeys(fn ($e) => [$e->slug => [
            'name' => $e->name,
            'window' => $e->departs_label,
            'times' => $e->time_slots,
            'days' => $e->days_label,
            'weekdays' => $e->allowed_weekdays,
            'season' => $e->season_starts_on && $e->season_ends_on ? [$e->season_starts_on, $e->season_ends_on] : null,
            'extras' => $e->extras_label,
        ]])->all(),
        'initial' => $initial,
        'months' => [App\Http\Requests\BookingCard\AvailabilityRequest::months()[0], last(App\Http\Requests\BookingCard\AvailabilityRequest::months())],
        'freeHours' => $policy['free_hours'] ?? null,
    ];
    $rowClass = 'flex w-full items-center justify-between gap-3 rounded-xl border border-navy-200 bg-white px-4 py-3 text-left transition hover:border-navy-950 aria-expanded:border-navy-950 aria-expanded:ring-1 aria-expanded:ring-navy-950';
    $rowLabel = 'block text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400';
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
                <div class="space-y-4">
                    <ul class="flex flex-wrap gap-2" aria-label="Quick facts">
                        @foreach ($facts as [$icon, $text])
                            <li class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-navy-800 ring-1 ring-navy-100"><i data-lucide="{{ $icon }}" class="h-4 w-4 text-brass-500"></i>{{ $text }}</li>
                        @endforeach
                    </ul>

                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        @foreach ($specs as [$icon, $label, $value])
                            <div class="rounded-2xl border border-navy-100 bg-white p-5">
                                <i data-lucide="{{ $icon }}" class="h-5 w-5 text-brass-500"></i>
                                <p class="mt-3 text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">{{ $label }}</p>
                                <p class="mt-1 text-lg font-extrabold text-navy-950">{{ $value }}</p>
                            </div>
                        @endforeach
                    </div>
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
                    <p class="mt-2 text-navy-500">Optional and free. Each one departs in its own time window: pick it in the booking card for as many hours as you book — or just book the boat.</p>
                    <div class="mt-6 grid gap-4 sm:grid-cols-2">
                        @foreach ($experiences as $experience)
                            {{-- Unavailable boats never link to booking: the card opens the experience details instead. --}}
                            <a href="{{ $available ? route('yachts.show', [$yacht->slug, 'experience' => $experience->slug]).'#book' : route('experiences.index').'#'.$experience->slug }}" @if ($available) data-pick-experience="{{ $experience->slug }}" @endif class="group flex items-center gap-4 rounded-2xl bg-white p-3 pr-5 ring-1 ring-navy-100 transition hover:ring-brass-400">
                                <img src="{{ asset($experience->image) }}" alt="" loading="lazy" class="h-20 w-20 shrink-0 rounded-xl object-cover">
                                <div class="min-w-0 flex-1">
                                    <p class="font-bold text-navy-950 group-hover:text-brass-600">{{ $experience->name }}</p>
                                    <p class="mt-0.5 text-xs text-navy-500">{{ $experience->days_label }} · departs {{ $experience->departs_label }}</p>
                                    <p class="mt-1 text-xs font-bold text-brass-600">{{ $experience->extras_label }}</p>
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
                        @if ($sample)
                            <div class="border-t border-navy-100 bg-navy-50/60 p-6">
                                <p class="text-sm font-bold text-navy-950">Example: {{ $sample->hours }}-hour weekday charter, {{ $sampleGuests }} {{ Str::plural('guest', $sampleGuests) }}</p>
                                <dl class="mt-4 space-y-2 text-sm">
                                    @foreach ($sample->lines as $line)
                                        <div class="flex justify-between gap-4"><dt class="text-navy-600">{{ $line->label }} <span class="text-navy-400">· {{ $line->description }}</span></dt><dd class="font-semibold text-navy-900">{{ money($line->amountCents, 'never') }}</dd></div>
                                    @endforeach
                                    <div class="flex justify-between gap-4"><dt class="text-navy-600">Taxes ({{ App\Support\Money::percent($pricing['tax_rate_bp']) }})</dt><dd class="font-semibold text-navy-900">{{ money($sample->taxCents, 'never') }}</dd></div>
                                    <div class="flex justify-between gap-4 border-t border-navy-200 pt-3 text-base"><dt class="font-bold text-navy-950">Total</dt><dd class="font-extrabold text-navy-950">{{ money($sample->totalCents, 'never') }}</dd></div>
                                </dl>
                                <p class="mt-3 text-xs text-navy-400">Paid in full at checkout. Crew gratuity is optional and added at checkout.</p>
                            </div>
                        @endif
                    </div>
                </div>

                <div>
                    <h2 class="font-display text-3xl text-navy-950">Things to know</h2>
                    <div class="mt-6 grid gap-6 md:grid-cols-3">
                        <div class="rounded-3xl bg-white p-6 ring-1 ring-navy-100">
                            <h3 class="flex items-center gap-2 text-lg font-bold text-navy-950"><i data-lucide="circle-check" class="h-5 w-5 text-emerald-600"></i>Included</h3>
                            <ul class="mt-4 space-y-2.5 text-sm text-navy-600">
                                <li>Licensed USCG captain{{ $yacht->crew_count > 1 ? ' & crew' : '' }}</li><li>Fuel for standard routes</li><li>Life jackets &amp; safety gear</li><li>Ice, water &amp; cups</li><li>Bluetooth sound system</li>
                            </ul>
                        </div>
                        <div class="rounded-3xl bg-white p-6 ring-1 ring-navy-100">
                            <h3 class="flex items-center gap-2 text-lg font-bold text-navy-950"><i data-lucide="info" class="h-5 w-5 text-brass-500"></i>On board</h3>
                            <ul class="mt-4 space-y-2.5 text-sm text-navy-600">
                                @foreach ($yacht->rules as $rule)
                                    <li>No {{ Str::lower($rule) }}</li>
                                @endforeach
                                <li>Arrive 20 minutes before departure</li><li>BYOB welcome (21+)</li><li>Crew gratuity not included</li>
                            </ul>
                        </div>
                        <div class="rounded-3xl bg-white p-6 ring-1 ring-navy-100">
                            <h3 class="flex items-center gap-2 text-lg font-bold text-navy-950"><i data-lucide="calendar-x" class="h-5 w-5 text-brass-500"></i>{{ $policy ? $policy['name'].' cancellation' : 'Cancellation' }}</h3>
                            <ul class="mt-4 space-y-2.5 text-sm text-navy-600">
                                @forelse ($policy['points'] ?? [] as $point)
                                    <li>{{ $point }}</li>
                                @empty
                                    <li>See the cancellation policy before you pay.</li>
                                @endforelse
                                <li>Weather cancellations by the captain: free reschedule or a full refund</li>
                            </ul>
                            <a href="{{ route('legal.cancellation') }}" class="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-brass-600 hover:text-brass-700">Full policy <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                        </div>
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

            {{-- Booking card (4.3, D21): the trip is picked right here, as on Boatsetter, in our design. Rows open
                 panels (calendar, lengths, start times) filled from the availability endpoint; the price comes from
                 the quote endpoint. On phones the card is a full-screen sheet opened from the bottom bar
                 (resources/js/pages/yacht.js). On large screens it follows the page and, when taller than the
                 screen, scrolls on its own (data-sticky-panel, resources/js/ui/sticky-panels.js). --}}
            <aside id="book" class="scroll-mt-28">
                <div class="lg:sticky lg:top-28 lg:-mx-4 lg:-mt-4 lg:max-h-[calc(100dvh-8rem)] lg:overflow-y-auto lg:px-4 lg:pt-4 lg:pb-4 no-scrollbar" data-sticky-panel>
                    @if ($available)
                        <div id="booking-sheet" data-booking-sheet class="no-scrollbar max-lg:hidden">
                            <div class="mb-4 flex items-center justify-between gap-4 lg:hidden">
                                <p class="font-display text-2xl text-navy-950">Book {{ $yacht->title }}</p>
                                <button type="button" data-sheet-close class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-white text-navy-950 ring-1 ring-navy-100" aria-label="Close the booking card"><i data-lucide="x" class="h-5 w-5"></i></button>
                            </div>
                            <form id="booking-card" action="{{ route('checkout.create') }}" method="get" novalidate data-booking-card="{{ json_encode($card) }}" class="overflow-hidden rounded-3xl bg-white shadow-card ring-1 ring-navy-100">
                                <input type="hidden" name="yacht" value="{{ $yacht->slug }}">
                                <input type="hidden" name="date" value="{{ $initial['date'] }}">
                                <input type="hidden" name="time" value="">
                                <input type="hidden" name="hours" value="{{ $initial['hours'] }}">
                                <input type="hidden" name="addons" value="{{ implode(',', $initial['addons']) }}">
                                <div class="bg-navy-950 p-6 text-white">
                                    <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-brass-300">From</p>
                                    <p class="mt-1 text-4xl font-extrabold">{{ money($yacht->hourly_rate_cents) }}<span class="text-base font-semibold text-white/50">/hr</span></p>
                                    <p class="mt-1 text-xs text-white/60">{{ $lengthsLabel }} · {{ $captainLabel }} {{ money($yacht->captain_rate_cents) }}/hr, its own line</p>
                                </div>
                                <div class="space-y-3 p-6">
                                    @if ($experiences->isNotEmpty())
                                        <label class="block">
                                            <span class="{{ $rowLabel }}">Experience · optional</span>
                                            <span class="relative mt-1.5 block">
                                                <select name="experience" class="w-full appearance-none rounded-xl border border-navy-200 bg-white py-3 pl-4 pr-10 text-sm font-semibold text-navy-950 focus:border-navy-950 focus:outline-hidden">
                                                    <option value="">Just the boat</option>
                                                    @foreach ($experiences as $experience)
                                                        <option value="{{ $experience->slug }}" @selected($initial['experience'] === $experience->slug)>{{ $experience->name }} · {{ $experience->departs_label }}</option>
                                                    @endforeach
                                                </select>
                                                <i data-lucide="chevron-down" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                                            </span>
                                            <span data-card="experience-note" class="mt-1.5 block text-xs text-navy-500"></span>
                                        </label>
                                        <p data-card="notice" hidden role="status" class="rounded-xl bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-900 ring-1 ring-amber-200"></p>
                                    @endif

                                    <div class="grid grid-cols-2 gap-3">
                                        <button type="button" data-row="date" aria-expanded="false" aria-controls="panel-date" class="{{ $rowClass }}">
                                            <span class="min-w-0"><span class="{{ $rowLabel }}">Date</span><span data-value="date" class="mt-0.5 block truncate text-sm font-bold text-navy-950">{{ $initial['date'] ? Carbon\CarbonImmutable::parse($initial['date'])->format('D, M j') : 'Pick a date' }}</span></span>
                                            <i data-lucide="calendar-days" class="h-4 w-4 shrink-0 text-navy-400"></i>
                                        </button>
                                        <button type="button" data-row="hours" aria-expanded="false" aria-controls="panel-hours" class="{{ $rowClass }}">
                                            <span class="min-w-0"><span class="{{ $rowLabel }}">Duration</span><span data-value="hours" class="mt-0.5 block truncate text-sm font-bold text-navy-950">{{ $initial['hours'] }} {{ Str::plural('hour', $initial['hours']) }}</span></span>
                                            <i data-lucide="clock" class="h-4 w-4 shrink-0 text-navy-400"></i>
                                        </button>
                                    </div>
                                    <div id="panel-date" data-panel="date" hidden class="rounded-2xl border border-navy-100 p-3"></div>
                                    <div id="panel-hours" data-panel="hours" hidden class="rounded-2xl border border-navy-100 p-3"></div>

                                    <button type="button" data-row="time" aria-expanded="false" aria-controls="panel-time" class="{{ $rowClass }}">
                                        <span class="min-w-0"><span class="{{ $rowLabel }}">Start time</span><span data-value="time" class="mt-0.5 block truncate text-sm font-bold text-navy-950">Pick a date first</span></span>
                                        <i data-lucide="timer" class="h-4 w-4 shrink-0 text-navy-400"></i>
                                    </button>
                                    <div id="panel-time" data-panel="time" hidden class="rounded-2xl border border-navy-100 p-3"></div>

                                    <div>
                                        <span id="guests-label" class="{{ $rowLabel }}">Guests</span>
                                        <div class="mt-1.5 flex items-center justify-between rounded-xl border border-navy-200 px-2 py-1.5">
                                            <button type="button" data-guests-step="-1" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-navy-50" aria-label="Fewer guests"><i data-lucide="minus" class="h-4 w-4"></i></button>
                                            <input name="guests" type="number" min="1" max="{{ $yacht->max_guests }}" value="{{ $initial['guests'] }}" inputmode="numeric" class="w-16 text-center text-sm font-bold focus:outline-hidden" aria-labelledby="guests-label">
                                            <button type="button" data-guests-step="1" class="grid h-9 w-9 place-items-center rounded-lg hover:bg-navy-50" aria-label="More guests"><i data-lucide="plus" class="h-4 w-4"></i></button>
                                        </div>
                                        <p class="mt-1 text-xs text-navy-400">Up to {{ $yacht->max_guests }} guests (USCG limit)</p>
                                    </div>

                                    <p class="flex items-center gap-2.5 rounded-xl bg-brass-50 px-4 py-3 text-sm font-semibold text-navy-800 ring-1 ring-brass-200"><i data-lucide="ship-wheel" class="h-4 w-4 shrink-0 text-brass-600"></i>{{ $yacht->crew_count > 1 ? 'Captained · captain + '.($yacht->crew_count - 1).' crew' : 'Captained · licensed USCG captain' }}</p>

                                    <div id="card-price" class="rounded-2xl bg-navy-50 p-4 text-sm" aria-live="polite">
                                        <dl data-card="lines" class="space-y-2">
                                            @if ($estimate)
                                                @foreach ([...$estimate->lines, ...$estimate->adjustments] as $line)
                                                    <div class="flex justify-between gap-4"><dt class="min-w-0 text-navy-600">{{ $line->label }}<span class="block text-xs text-navy-400">{{ $line->description }}</span></dt><dd class="shrink-0 font-semibold text-navy-900">{{ money($line->amountCents) }}</dd></div>
                                                @endforeach
                                            @endif
                                        </dl>
                                        <div class="mt-3 flex items-end justify-between gap-4 border-t border-navy-200 pt-3"><span class="font-bold text-navy-950">Total</span><span data-card="total" class="text-lg font-extrabold text-navy-950">{{ $estimate ? money($estimate->totalCents) : '—' }}</span></div>
                                        <p data-card="note" class="mt-2 text-xs text-navy-500">{{ $initial['date'] ? 'Paid in full at checkout.' : 'Weekday price. Pick a date for yours.' }}</p>
                                    </div>
                                    <p data-card="error" @unless (session('booking_card_error')) hidden @endunless class="rounded-xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700" role="alert">{{ session('booking_card_error') }}</p>

                                    <x-button type="submit" icon="arrow-right" size="block">Book now</x-button>
                                    <p data-card="cancellation" class="flex items-center justify-center gap-2 text-center text-xs font-semibold text-emerald-700"><i data-lucide="calendar-check" class="h-3.5 w-3.5"></i><span data-card="cancellation-text">{{ $policy && $policy['free_hours'] ? 'Free cancellation up to '.($policy['free_hours'] % 24 === 0 ? ($policy['free_hours'] / 24).' '.Str::plural('day', $policy['free_hours'] / 24) : $policy['free_hours'].' hours').' before' : ($policy ? $policy['name'].' cancellation policy' : 'Cancellation policy shown at checkout') }}</span></p>
                                    <p class="flex items-center justify-center gap-2 text-xs text-navy-400"><i data-lucide="lock" class="h-3.5 w-3.5"></i>You won't be charged yet</p>
                                </div>
                            </form>
                        </div>
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
                <div class="min-w-0"><p class="truncate text-xs text-navy-400" data-bar="summary">{{ $initial['hours'] }} {{ Str::plural('hr', $initial['hours']) }} · {{ $initial['date'] ? 'total' : 'weekday price' }}</p><p class="text-xl font-extrabold text-navy-950" data-bar="total">{{ $estimate ? money($estimate->totalCents, 'never') : money($yacht->hourly_rate_cents).'/hr' }}</p></div>
                <a href="#book" data-sheet-open class="inline-flex shrink-0 items-center justify-center gap-2 rounded-full bg-brass-400 px-7 py-3 text-sm font-bold text-navy-950 shadow-glow transition hover:bg-brass-300">Book now</a>
            @else
                <div><p class="text-xs text-navy-400">{{ $yacht->title }}</p><p class="text-base font-extrabold text-navy-950">Currently unavailable</p></div>
                <a href="{{ route('yachts.index') }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-navy-950 px-6 py-3 text-sm font-bold text-white">See yachts</a>
            @endif
        </div>
    </div>
    @endpush
@endsection
