@extends('layouts.app', ['page' => 'home'])

@php
    $business = config('site.business');

    // Curated home-page copy (Phase 10: editable "home sections").
    $destinations = [
        ['slug' => 'playpen', 'label' => 'Swim & Party', 'title' => 'The Playpen', 'text' => "Anchor, float and swim at Chicago's favorite on-the-water hangout.", 'image' => 'images/exp-swim.jpg', 'alt' => 'Friends on the bow of a yacht in turquoise water'],
        ['slug' => 'river', 'label' => 'Sightseeing', 'title' => 'Chicago River', 'text' => 'Cruise the canyon of skyscrapers on a private architecture tour.', 'image' => 'images/chicago-river-bridges.jpg', 'alt' => 'Raised bridges over the Chicago River'],
        ['slug' => 'fireworks', 'label' => 'Wed & Sat nights', 'title' => 'Navy Pier Fireworks', 'text' => 'Front-row, private seats to the summer fireworks show.', 'image' => 'images/exp-fireworks.jpg', 'alt' => 'Couple watching fireworks over the water'],
    ];
    $occasions = [
        ['slug' => 'celebration', 'text' => 'Décor, champagne flutes and a celebration playlist, ready when you board.', 'image' => 'images/exp-friends.jpg'],
        ['slug' => 'corporate', 'text' => 'Client entertaining and team events with catering coordination and invoicing.', 'image' => 'images/exp-toast.jpg'],
        ['slug' => 'proposal', 'text' => 'Roses, champagne and a private sunset route planned down to the minute.', 'image' => 'images/exp-couple.jpg'],
        ['slug' => 'sunset', 'text' => 'Golden hour on the lake as the city lights flicker on.', 'image' => 'images/hero-poster.jpg'],
        ['slug' => 'private', 'text' => 'Your yacht, your route — skyline, beaches or simply open water.', 'image' => 'images/yacht-aerial.jpg'],
    ];
    $experienceNames = $experiences->pluck('name', 'slug');
    $whyUs = [
        ['icon' => 'ship-wheel', 'title' => 'Captain included', 'text' => 'Every package comes with a licensed, background-checked captain. No extra hiring.'],
        ['icon' => 'cloud-sun', 'title' => 'Weather guarantee', 'text' => 'If the captain calls it, you reschedule free or get a full refund.'],
        ['icon' => 'badge-dollar-sign', 'title' => 'Transparent pricing', 'text' => 'See your full total before checkout — charter, captain, fees and tax.'],
        ['icon' => 'zap', 'title' => 'Instant booking', 'text' => 'Live calendar, real time slots, and instant confirmation in under 3 minutes.'],
    ];
    $steps = [
        ['title' => 'Choose your experience', 'text' => 'Sunset, Playpen, fireworks, river tour or a private charter — then pick the yacht that fits your group.'],
        ['title' => 'Pick date, time & duration', 'text' => 'See live availability on the calendar and choose a departure time that works for you.'],
        ['title' => 'Check out & set sail', 'text' => 'Secure your date with a 50% deposit. Your captain meets you at the dock — just bring the crew.'],
    ];
    $guestOptions = ['2' => '2 guests', '4' => '3–4 guests', '6' => '5–6 guests', '10' => '7–10 guests', '13' => '11–13 guests', '30' => '14–30 guests', '49' => '31–49 guests', '80' => '50–80 guests'];
    $tabOn = 'rounded-full bg-navy-950 px-5 py-2.5 text-sm font-bold text-white';
    $tabOff = 'rounded-full border border-navy-200 px-5 py-2.5 text-sm font-bold text-navy-700 hover:border-navy-950';
@endphp

@section('content')
    {{-- ============================== HERO (background video) ============================== --}}
    <section class="relative isolate flex min-h-[100svh] flex-col overflow-hidden bg-navy-950">
        <video id="hero-video" class="hero-video absolute inset-0 -z-20 h-full w-full object-cover" autoplay muted loop playsinline preload="auto" poster="{{ asset('images/hero-poster.jpg') }}" aria-hidden="true">
            <source src="{{ asset('video/hero.mp4') }}" type="video/mp4">
        </video>
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-navy-950/75 via-navy-950/35 to-navy-950/90"></div>
        <div class="absolute inset-0 -z-10 bg-[radial-gradient(ellipse_at_center,transparent_30%,rgba(7,17,30,.55)_100%)]"></div>

        <div class="container flex flex-1 flex-col items-center justify-center pb-10 pt-40 text-center md:pt-44">
            <p class="reveal inline-flex items-center gap-3 rounded-full border border-white/20 bg-white/5 px-4 py-2 text-[11px] font-bold uppercase tracking-[0.3em] text-brass-200 backdrop-blur">
                <i data-lucide="anchor" class="h-3.5 w-3.5"></i> Chicago's Premier Yacht Charters
            </p>
            <h1 class="reveal mt-7 max-w-5xl font-display text-[2.6rem] leading-[1.05] text-white sm:text-6xl sm:leading-none lg:text-[5.25rem]" data-delay="1">
                Your Private Yacht <br class="hidden sm:block"><em class="font-medium text-brass-300">on Lake Michigan</em>
            </h1>
            <p class="reveal mx-auto mt-6 max-w-2xl text-base leading-relaxed text-white/80 sm:text-lg sm:leading-7" data-delay="2">
                Skyline sunsets, Playpen swim parties, Navy Pier fireworks and river architecture cruises. Every package includes a licensed captain — for groups of 2 to 80, seven days a week.
            </p>
            <div class="reveal mt-9 flex flex-col items-center gap-3 sm:flex-row" data-delay="3">
                <a href="{{ route('booking.create') }}" class="inline-flex items-center gap-2 rounded-full bg-brass-400 px-8 py-4 text-sm font-bold text-navy-950 shadow-glow transition hover:-translate-y-0.5 hover:bg-brass-300">Book Your Yacht <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                <a href="{{ route('pricing') }}" class="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/5 px-8 py-4 text-sm font-bold text-white backdrop-blur transition hover:bg-white hover:text-navy-950">See Pricing</a>
                <a href="tel:{{ $business['phone_href'] }}" class="inline-flex items-center gap-2 px-4 py-4 text-sm font-bold text-white/85 hover:text-white"><i data-lucide="phone" class="h-4 w-4 text-brass-300"></i>{{ $business['phone'] }}</a>
            </div>
        </div>

        {{-- Quick booking bar: a plain GET form to /book (works without JS) --}}
        <div class="container relative pb-8 md:pb-12">
            <form action="{{ route('booking.create') }}" method="get" data-quick-book class="reveal mx-auto grid max-w-5xl gap-2 rounded-3xl border border-white/15 bg-white/10 p-2 shadow-2xl backdrop-blur-xl md:grid-cols-[1.3fr_1fr_0.8fr_auto]" data-delay="4">
                <label class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-left">
                    <i data-lucide="compass" class="h-5 w-5 shrink-0 text-brass-500"></i>
                    <span class="flex-1">
                        <span class="block text-[10px] font-bold uppercase tracking-[0.2em] text-navy-400">Experience</span>
                        <select name="experience" class="w-full cursor-pointer bg-transparent text-sm font-semibold text-navy-950 focus:outline-hidden">
                            <option value="">Any experience</option>
                            @foreach ($experiences as $experience)
                                <option value="{{ $experience->slug }}">{{ $experience->name }}</option>
                            @endforeach
                        </select>
                    </span>
                </label>
                <label class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-left">
                    <i data-lucide="calendar-days" class="h-5 w-5 shrink-0 text-brass-500"></i>
                    <span class="flex-1">
                        <span class="block text-[10px] font-bold uppercase tracking-[0.2em] text-navy-400">Date</span>
                        <input type="date" name="date" min="{{ $today }}" class="w-full cursor-pointer bg-transparent text-sm font-semibold text-navy-950 focus:outline-hidden">
                    </span>
                </label>
                <label class="flex items-center gap-3 rounded-2xl bg-white px-4 py-3 text-left">
                    <i data-lucide="users" class="h-5 w-5 shrink-0 text-brass-500"></i>
                    <span class="flex-1">
                        <span class="block text-[10px] font-bold uppercase tracking-[0.2em] text-navy-400">Guests</span>
                        <select name="guests" class="w-full cursor-pointer bg-transparent text-sm font-semibold text-navy-950 focus:outline-hidden">
                            @foreach ($guestOptions as $value => $label)
                                <option value="{{ $value }}" @selected($value === '10')>{{ $label }}</option>
                            @endforeach
                        </select>
                    </span>
                </label>
                <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-brass-400 px-7 py-4 text-sm font-bold text-navy-950 transition hover:bg-brass-300">
                    <i data-lucide="search" class="h-4 w-4"></i> Check Availability
                </button>
            </form>
            <div class="mt-6 hidden items-center justify-between text-white/60 md:flex">
                <button type="button" id="video-toggle" class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] hover:text-white" aria-label="Pause background video">
                    <i data-lucide="pause" class="h-4 w-4"></i><span>Pause video</span>
                </button>
                <a href="#fleet" class="flex flex-col items-center gap-2 text-[10px] font-bold uppercase tracking-[0.3em] hover:text-white">
                    <span class="relative h-9 w-5 rounded-full border border-white/40" aria-hidden="true"><span class="absolute left-1/2 top-1.5 h-2 w-1 -translate-x-1/2 animate-scroll-dot rounded-full bg-brass-300"></span></span>
                    Scroll
                </a>
                <p class="text-xs font-semibold uppercase tracking-[0.2em]">Departing daily · {{ $stats->last()->value }} Chicago harbors</p>
            </div>
        </div>
    </section>

    {{-- ============================== SOCIAL PROOF ============================== --}}
    <section class="border-b border-navy-100 bg-white">
        <div class="container grid items-center gap-10 py-12 lg:grid-cols-[1fr_2fr]">
            <div class="reveal flex items-center gap-5">
                <div class="grid h-16 w-16 shrink-0 place-items-center rounded-2xl bg-navy-950 font-display text-2xl text-brass-300">{{ number_format($rating->value, 1) }}</div>
                <div>
                    <x-stars :rating="$rating->value" size="h-5 w-5" />
                    <p class="mt-1 text-sm font-semibold text-navy-700">Rated {{ number_format($rating->value, 1) }} from <span class="text-navy-950">{{ $rating->count }}</span> guest reviews</p>
                </div>
            </div>
            <div class="grid grid-cols-2 gap-6 sm:grid-cols-4">
                @foreach ($stats as $stat)
                    <div class="reveal" data-delay="{{ $loop->iteration }}">
                        <p class="font-display text-4xl text-navy-950"><span data-count="{{ $stat->value }}" data-suffix="{{ $stat->suffix }}">{{ number_format($stat->value).$stat->suffix }}</span></p>
                        <p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-navy-400">{{ $stat->label }}</p>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="relative overflow-hidden border-t border-navy-100 py-6" aria-label="Trusted by Chicago teams">
            <div class="pointer-events-none absolute inset-y-0 left-0 z-10 w-24 bg-gradient-to-r from-white to-transparent"></div>
            <div class="pointer-events-none absolute inset-y-0 right-0 z-10 w-24 bg-gradient-to-l from-white to-transparent"></div>
            <div class="flex w-max animate-marquee items-center gap-16 whitespace-nowrap font-display text-xl text-navy-300">
                @foreach ([false, true] as $duplicate)
                    <span class="font-sans text-xs font-bold uppercase tracking-[0.3em] text-navy-400" @if ($duplicate) aria-hidden="true" @endif>Trusted by Chicago teams</span>
                    @foreach ($trustedBy as $company)
                        <span @if ($duplicate) aria-hidden="true" @endif>{{ $company }}</span><span aria-hidden="true">◆</span>
                    @endforeach
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================== FEATURED FLEET (tabs) ============================== --}}
    <section id="fleet" class="py-24 md:py-32">
        <div class="container">
            <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
                <x-section-heading eyebrow="Our Fleet" class="reveal max-w-2xl" subtitle="From intimate sailboats for two to an 80-guest party catamaran — every yacht is inspected, insured and run by a licensed USCG captain.">
                    Find the right yacht <em>for your crew</em>
                </x-section-heading>
                <div class="reveal flex flex-wrap gap-2" role="tablist" aria-label="Filter fleet by group size" data-tabs data-tab-on="{{ $tabOn }}" data-tab-off="{{ $tabOff }}">
                    @foreach ($fleetTabs as $key => $tab)
                        <button type="button" role="tab" id="fleet-tab-{{ $key }}" aria-controls="fleet-panel-{{ $key }}"
                                aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}"
                                class="{{ $loop->first ? $tabOn : $tabOff }}">{{ $tab['label'] }}</button>
                    @endforeach
                </div>
            </div>
            @foreach ($fleetTabs as $key => $tab)
                <div id="fleet-panel-{{ $key }}" role="tabpanel" aria-labelledby="fleet-tab-{{ $key }}" class="mt-14 grid gap-8 md:grid-cols-2 lg:grid-cols-3" @unless ($loop->first) hidden @endunless>
                    @foreach ($tab['yachts'] as $yacht)
                        <x-yacht-card :yacht="$yacht" :delay="$loop->index" />
                    @endforeach
                </div>
            @endforeach
            <div class="reveal mt-14 text-center">
                <x-button :href="route('yachts.index')" variant="outline" size="lg" icon="arrow-right">View the full fleet</x-button>
            </div>
        </div>
    </section>

    {{-- ============================== EXPERIENCES ============================== --}}
    <section class="relative overflow-hidden bg-navy-950 py-24 text-white md:py-32">
        <div class="pointer-events-none absolute -left-40 top-20 h-[30rem] w-[30rem] rounded-full bg-brass-400/10 blur-3xl" aria-hidden="true"></div>
        <div class="container relative">
            <x-section-heading eyebrow="Experiences & Events" center dark class="reveal mx-auto max-w-3xl" subtitle="Pick a signature experience — we'll match you with the right yacht, the right route and the perfect departure time.">
                Chicago's best moments are <em>on the water</em>
            </x-section-heading>

            <div class="mt-16 grid gap-6 md:grid-cols-3">
                @foreach ($destinations as $destination)
                    <a href="{{ route('booking.create', ['experience' => $destination['slug']]) }}" @class(['reveal group relative block aspect-[3/4] overflow-hidden rounded-3xl', 'md:mt-12' => $loop->index === 1]) @if ($loop->index) data-delay="{{ $loop->index }}" @endif>
                        <img src="{{ asset($destination['image']) }}" alt="{{ $destination['alt'] }}" loading="lazy" class="absolute inset-0 h-full w-full object-cover transition duration-[1.4s] group-hover:scale-110">
                        <div class="absolute inset-0 bg-gradient-to-t from-navy-950 via-navy-950/20 to-transparent"></div>
                        <span class="absolute left-5 top-5 rounded-full bg-white/15 px-3 py-1 text-[11px] font-bold uppercase tracking-wider backdrop-blur">{{ $destination['label'] }}</span>
                        <div class="absolute inset-x-0 bottom-0 p-7">
                            <h3 class="font-display text-3xl">{{ $destination['title'] }}</h3>
                            <p class="mt-2 text-sm text-white/70">{{ $destination['text'] }}</p>
                            <span class="mt-5 inline-flex items-center gap-2 text-sm font-bold text-brass-300">Book this experience <i data-lucide="arrow-right" class="h-4 w-4 transition group-hover:translate-x-1"></i></span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-24 grid gap-12 lg:grid-cols-[1fr_1.4fr] lg:gap-20">
                <div class="reveal lg:sticky lg:top-32 lg:self-start">
                    <p class="text-xs font-bold uppercase tracking-[0.3em] text-brass-300">Your Chicago Moment</p>
                    <h3 class="mt-4 font-display text-4xl leading-tight">Whatever you're celebrating, there's a yacht for it.</h3>
                    <p class="mt-4 text-white/65">Tell us the occasion and we'll handle the details — décor, catering, playlists and the perfect route.</p>
                    <div class="relative mt-8 overflow-hidden rounded-3xl">
                        <img src="{{ asset('images/exp-party.jpg') }}" alt="Guests celebrating on a luxury yacht at sunset" loading="lazy" class="aspect-[4/3] w-full object-cover">
                    </div>
                </div>
                <div class="divide-y divide-white/10 border-y border-white/10">
                    @foreach ($occasions as $occasion)
                        <a href="{{ route('booking.create', ['experience' => $occasion['slug']]) }}" class="reveal group flex items-center gap-5 py-6">
                            <img src="{{ asset($occasion['image']) }}" alt="" loading="lazy" class="h-20 w-20 shrink-0 rounded-2xl object-cover md:h-24 md:w-28">
                            <div class="flex-1">
                                <h4 class="font-display text-2xl transition group-hover:text-brass-300">{{ $experienceNames[$occasion['slug']] }}</h4>
                                <p class="mt-1 text-sm text-white/60">{{ $occasion['text'] }}</p>
                            </div>
                            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full border border-white/20 transition group-hover:border-brass-400 group-hover:bg-brass-400 group-hover:text-navy-950"><i data-lucide="arrow-up-right" class="h-5 w-5"></i></span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============================== WHY US ============================== --}}
    <section class="py-24 md:py-32">
        <div class="container grid items-center gap-16 lg:grid-cols-2">
            <div class="reveal relative">
                <div class="grid grid-cols-5 grid-rows-6 gap-4">
                    <img src="{{ asset('images/deck-lounge.jpg') }}" alt="Yacht deck lounge with cushions" loading="lazy" class="col-span-3 row-span-6 h-full w-full rounded-3xl object-cover">
                    <img src="{{ asset('images/chicago-navy-pier.jpg') }}" alt="Chicago skyline and Navy Pier from the lake" loading="lazy" class="col-span-2 row-span-3 h-full w-full rounded-3xl object-cover">
                    <img src="{{ asset('images/exp-cabin.jpg') }}" alt="Guests relaxing inside a yacht cabin" loading="lazy" class="col-span-2 row-span-3 h-full w-full rounded-3xl object-cover">
                </div>
                <div class="absolute -bottom-8 left-8 flex items-center gap-4 rounded-2xl bg-white p-5 shadow-card md:left-12">
                    <span class="grid h-12 w-12 place-items-center rounded-full bg-brass-100 text-brass-700"><i data-lucide="shield-check" class="h-6 w-6"></i></span>
                    <div><p class="font-display text-xl text-navy-950">USCG Licensed</p><p class="text-xs font-semibold text-navy-400">Fully insured · Safety-certified</p></div>
                </div>
            </div>
            <div>
                <div class="reveal">
                    <x-section-heading eyebrow="Why Chicago Yacht Booking">
                        Local captains. <em>Honest pricing.</em> Zero hassle.
                    </x-section-heading>
                    <p class="mt-5 leading-relaxed text-navy-500">We're Chicago boaters who got tired of hidden fees and confusing booking. So we built a better way: real-time availability, all-in packages with your captain already included, and a team that answers the phone.</p>
                </div>
                <div class="mt-10 grid gap-5 sm:grid-cols-2">
                    @foreach ($whyUs as $item)
                        <div class="reveal rounded-3xl border border-navy-100 bg-white p-6" data-delay="{{ $loop->iteration }}">
                            <i data-lucide="{{ $item['icon'] }}" class="h-8 w-8 text-brass-500"></i>
                            <h3 class="mt-4 text-lg font-bold text-navy-950">{{ $item['title'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-navy-500">{{ $item['text'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- ============================== 3 STEPS ============================== --}}
    <section class="bg-white py-24 md:py-32">
        <div class="container">
            <x-section-heading eyebrow="How it works" center class="reveal mx-auto max-w-2xl">
                Book your Chicago yacht in <em>3 easy steps</em>
            </x-section-heading>
            <ol class="relative mt-16 grid gap-8 md:grid-cols-3">
                <li class="pointer-events-none absolute left-[16%] right-[16%] top-10 hidden border-t-2 border-dashed border-navy-100 md:block" aria-hidden="true"></li>
                @foreach ($steps as $step)
                    <li class="reveal relative text-center" @if ($loop->index) data-delay="{{ $loop->index }}" @endif>
                        <span @class([
                            'relative mx-auto grid h-20 w-20 place-items-center rounded-full font-display text-2xl ring-8 ring-white',
                            'bg-navy-950 text-brass-300' => ! $loop->last,
                            'bg-brass-400 text-navy-950' => $loop->last,
                        ])>{{ sprintf('%02d', $loop->iteration) }}</span>
                        <h3 class="mt-6 text-xl font-bold text-navy-950">{{ $step['title'] }}</h3>
                        <p class="mx-auto mt-2 max-w-xs text-sm leading-relaxed text-navy-500">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
            <div class="reveal mt-14 text-center">
                <x-button :href="route('booking.create')" variant="dark" size="xl" icon="arrow-right">Start Booking</x-button>
            </div>
        </div>
    </section>

    {{-- ============================== TESTIMONIALS (carousel) ============================== --}}
    <section class="py-24 md:py-32" data-carousel aria-roledescription="carousel" aria-label="Guest reviews">
        <div class="container">
            <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
                <x-section-heading eyebrow="Guest Reviews" class="reveal max-w-2xl">
                    Loved by <em>{{ $rating->count }}</em> happy crews
                </x-section-heading>
                <div class="reveal flex items-center gap-4">
                    <span data-counter class="font-display text-lg text-navy-400" aria-hidden="true">01 / {{ sprintf('%02d', $testimonials->count()) }}</span>
                    <button type="button" data-prev class="grid h-12 w-12 place-items-center rounded-full border border-navy-200 text-navy-900 transition hover:bg-navy-950 hover:text-white" aria-label="Previous review"><i data-lucide="arrow-left" class="h-5 w-5"></i></button>
                    <button type="button" data-next class="grid h-12 w-12 place-items-center rounded-full bg-navy-950 text-white transition hover:bg-brass-400 hover:text-navy-950" aria-label="Next review"><i data-lucide="arrow-right" class="h-5 w-5"></i></button>
                </div>
            </div>
        </div>
        <div class="container mt-12">
            <div data-track class="no-scrollbar flex snap-x snap-mandatory gap-6 overflow-x-auto pb-4" tabindex="0" aria-label="Reviews, scroll sideways">
                @foreach ($testimonials as $review)
                    @php($initials = collect(explode(' ', $review->name))->map(fn ($part) => mb_substr($part, 0, 1))->implode(''))
                    <figure @class([
                        'flex w-[88%] shrink-0 snap-start flex-col rounded-3xl p-8 shadow-card sm:w-[60%] lg:w-[calc((100%-3rem)/3)]',
                        'bg-navy-950 text-white' => $review->featured,
                        'bg-white' => ! $review->featured,
                    ])>
                        <i data-lucide="quote" class="h-8 w-8 text-brass-300"></i>
                        <blockquote @class(['mt-4 flex-1 leading-relaxed', 'text-white/80' => $review->featured, 'text-navy-700' => ! $review->featured])>"{{ $review->quote }}"</blockquote>
                        <figcaption @class(['mt-6 flex items-center gap-4 border-t pt-5', 'border-white/10' => $review->featured, 'border-navy-100' => ! $review->featured])>
                            <span @class(['grid h-11 w-11 place-items-center rounded-full text-sm font-bold', 'bg-brass-400 text-navy-950' => $review->featured, 'bg-navy-950 text-brass-300' => ! $review->featured]) aria-hidden="true">{{ $initials }}</span>
                            <div>
                                <p @class(['font-bold', 'text-navy-950' => ! $review->featured])>{{ $review->name }}</p>
                                <p @class(['text-xs', 'text-white/50' => $review->featured, 'text-navy-400' => ! $review->featured])>{{ $review->trip }}</p>
                            </div>
                            <x-stars :rating="$review->rating" class="ml-auto" />
                        </figcaption>
                    </figure>
                @endforeach
            </div>
            <div class="mt-6 h-1 w-full overflow-hidden rounded-full bg-navy-100" aria-hidden="true"><div data-progress class="h-full w-[12%] rounded-full bg-brass-400 transition-all duration-300"></div></div>
        </div>
    </section>

    {{-- ============================== GALLERY ============================== --}}
    <section class="bg-white py-24 md:py-32">
        <div class="container">
            <div class="reveal flex flex-col justify-between gap-6 md:flex-row md:items-end">
                <x-section-heading eyebrow="Life on the lake">Moments from the water</x-section-heading>
                <a href="{{ config('site.social.instagram') }}" class="inline-flex items-center gap-2 text-sm font-bold text-navy-900 hover:text-brass-600"><i data-lucide="instagram" class="h-5 w-5"></i>@chicagoyachtbooking</a>
            </div>
            <div class="mt-12 grid auto-rows-[160px] grid-cols-2 gap-4 md:auto-rows-[200px] md:grid-cols-4" data-lightbox-gallery>
                @foreach ($gallery as $photo)
                    <button type="button" data-lightbox="{{ asset($photo->image) }}" aria-label="Open photo: {{ $photo->alt }}"
                            @class([
                                'reveal group relative overflow-hidden rounded-3xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass-400',
                                'col-span-2 row-span-2' => $loop->index === 0,
                                'row-span-2' => $loop->index === 2,
                            ])
                            @if ($loop->index % 4) data-delay="{{ $loop->index % 4 }}" @endif>
                        <img src="{{ asset($photo->image) }}" alt="{{ $photo->alt }}" loading="lazy" class="h-full w-full object-cover transition duration-700 group-hover:scale-105">
                    </button>
                @endforeach
            </div>
        </div>
    </section>

    {{-- ============================== FAQ ============================== --}}
    <section class="py-24 md:py-32">
        <div class="container grid gap-14 lg:grid-cols-[1fr_1.5fr]">
            <div class="reveal">
                <x-section-heading eyebrow="FAQ" subtitle="Can't find what you're looking for? Our crew is on the phone every day.">
                    Questions before you set sail?
                </x-section-heading>
                <div class="mt-8 rounded-3xl bg-navy-950 p-7 text-white">
                    <p class="font-display text-2xl">Talk to a charter expert</p>
                    <p class="mt-2 text-sm text-white/60">{{ $business['hours'] }}</p>
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row lg:flex-col xl:flex-row">
                        <a href="tel:{{ $business['phone_href'] }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-brass-400 px-6 py-3 text-sm font-bold text-navy-950 hover:bg-brass-300"><i data-lucide="phone" class="h-4 w-4"></i>Call us</a>
                        <a href="{{ route('contact') }}" class="inline-flex items-center justify-center gap-2 rounded-full border border-white/20 px-6 py-3 text-sm font-bold hover:bg-white hover:text-navy-950"><i data-lucide="message-circle" class="h-4 w-4"></i>Send a message</a>
                    </div>
                </div>
            </div>
            <x-accordion class="reveal">
                @foreach ($faqs as $faq)
                    <x-accordion.item :question="$faq->question" :open="$loop->first">{{ $faq->answer }}</x-accordion.item>
                @endforeach
            </x-accordion>
        </div>
    </section>

    {{-- ============================== CTA ============================== --}}
    <section class="px-4 pb-24 md:pb-32">
        <div class="reveal relative isolate mx-auto max-w-7xl overflow-hidden rounded-[2.5rem] bg-navy-950 px-6 py-20 text-center text-white md:py-28">
            <img src="{{ asset('images/chicago-river-night.jpg') }}" alt="" loading="lazy" class="absolute inset-0 -z-10 h-full w-full object-cover opacity-40">
            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950 via-navy-950/60 to-navy-950/30"></div>
            <p class="text-xs font-bold uppercase tracking-[0.3em] text-brass-300">Summer dates are filling fast</p>
            <h2 class="mx-auto mt-4 max-w-3xl font-display text-4xl leading-tight md:text-6xl md:leading-none">Ready to see Chicago <em class="text-brass-300">from the water?</em></h2>
            <p class="mx-auto mt-5 max-w-xl text-white/70">Pick your yacht, choose your experience and lock in your date in under three minutes.</p>
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ route('booking.create') }}" class="inline-flex items-center gap-2 rounded-full bg-brass-400 px-8 py-4 text-sm font-bold text-navy-950 shadow-glow transition hover:-translate-y-0.5 hover:bg-brass-300">Book Now <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                <a href="{{ route('yachts.index') }}" class="inline-flex items-center gap-2 rounded-full border border-white/30 px-8 py-4 text-sm font-bold text-white transition hover:bg-white hover:text-navy-950">Browse Yachts</a>
            </div>
        </div>
    </section>
@endsection
