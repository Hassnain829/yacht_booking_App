@extends('layouts.app', [
    'title' => 'About Us',
    'description' => 'Chicago Yacht Booking began with one sailboat at Belmont Harbor. Meet our USCG-licensed captains and the four Chicago harbors we sail from.',
    'page' => 'about',
])

@php
    $values = [
        ['shield-check', 'Safety first, always', 'USCG-licensed captains, fully insured yachts, inspected safety gear and conservative weather calls. No exceptions.'],
        ['compass', 'Local expertise', 'Our captains know every corner of the lakefront — where to anchor, when the light is perfect and how to skip the crowds.'],
        ['heart-handshake', 'Real hospitality', 'From the first call to the last line tied, we treat every booking like a celebration — because it usually is.'],
    ];
@endphp

@section('content')
    <x-page-hero image="images/chicago-navy-pier.jpg" crumb="About Us"
                 intro="We started with one boat and a simple idea: booking a yacht in Chicago should be as easy and honest as booking a table.">
        Chicago boaters, <em>at your service</em>
    </x-page-hero>

    {{-- Story --}}
    <section class="py-24 md:py-32">
        <div class="container grid items-center gap-16 lg:grid-cols-2">
            <div class="reveal relative">
                <img src="{{ asset('images/hero-poster.jpg') }}" alt="Yacht docked in front of the Chicago skyline" loading="lazy" class="aspect-[4/5] w-full rounded-[2rem] object-cover">
                {{-- Offsets never exceed the container padding (mockup used -right-10 at md → 16px page overflow on tablets). --}}
                <div class="absolute -right-4 bottom-10 w-48 overflow-hidden rounded-3xl border-8 border-shell shadow-card md:-right-6 md:w-64 lg:-right-10">
                    <img src="{{ asset('images/yacht-windward.jpg') }}" alt="Sailing yacht on calm water" loading="lazy" class="aspect-square w-full object-cover">
                </div>
                <div class="absolute -left-4 top-10 rounded-2xl bg-brass-400 p-5 text-navy-950 shadow-glow md:-left-6 lg:-left-8">
                    <p class="font-display text-4xl">2016</p><p class="text-xs font-bold uppercase tracking-[0.18em]">On the water since</p>
                </div>
            </div>
            <div>
                <x-section-heading eyebrow="Our story" class="reveal">
                    From one sailboat to <em>Chicago's favorite</em> charter fleet
                </x-section-heading>
                <div class="reveal mt-6 space-y-4 text-lg leading-relaxed text-navy-600" data-delay="1">
                    <p>Chicago Yacht Booking began with a single sailboat at Belmont Harbor and a captain who loved showing friends the skyline from the lake. Word spread, the calendar filled, and we realized something: people wanted the experience, but hated the hassle.</p>
                    <p>So we built the company we'd want to book with. Live availability. Clear, all-in pricing with the captain already included. A team that picks up the phone. Today we run a hand-picked fleet across four Chicago harbors — and every charter still gets the same care as that very first sail.</p>
                </div>
                <div class="reveal mt-10 grid grid-cols-3 gap-6 border-t border-navy-100 pt-8" data-delay="2">
                    <div><p class="font-display text-4xl text-navy-950"><span data-count="25" data-suffix="K+">25K+</span></p><p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-navy-400">Guests</p></div>
                    <div><p class="font-display text-4xl text-navy-950"><span data-count="{{ $rating->value }}" data-decimals="1">{{ number_format($rating->value, 1) }}</span></p><p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-navy-400">Avg. rating</p></div>
                    <div><p class="font-display text-4xl text-navy-950"><span>0</span></p><p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-navy-400">Hidden fees</p></div>
                </div>
            </div>
        </div>
    </section>

    {{-- Values --}}
    <section class="bg-navy-950 py-24 text-white md:py-32">
        <div class="container">
            <div class="reveal mx-auto max-w-2xl text-center">
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-brass-300">What we stand for</p>
                <h2 class="mt-4 font-display text-4xl leading-tight md:text-5xl md:leading-none">Three promises on every charter</h2>
            </div>
            <div class="mt-16 grid gap-6 md:grid-cols-3">
                @foreach ($values as [$icon, $title, $text])
                    <div class="reveal rounded-3xl border border-white/10 bg-white/5 p-8" @if ($loop->index) data-delay="{{ $loop->index }}" @endif>
                        <span class="grid h-14 w-14 place-items-center rounded-2xl bg-brass-400 text-navy-950"><i data-lucide="{{ $icon }}" class="h-7 w-7"></i></span>
                        <h3 class="mt-6 font-display text-2xl">{{ $title }}</h3>
                        <p class="mt-3 text-sm leading-relaxed text-white/60">{{ $text }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Crew --}}
    <section class="py-24 md:py-32">
        <div class="container">
            <div class="reveal flex flex-col justify-between gap-6 md:flex-row md:items-end">
                <x-section-heading eyebrow="Meet the crew" class="max-w-2xl">Captains who love this lake</x-section-heading>
                <p class="max-w-sm text-sm text-navy-500">Every captain is USCG licensed, CPR certified and background checked.</p>
            </div>
            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($team as $member)
                    <div class="reveal rounded-3xl bg-white p-7 text-center shadow-card ring-1 ring-navy-100" @if ($loop->index) data-delay="{{ $loop->index }}" @endif>
                        <span @class(['mx-auto grid h-24 w-24 place-items-center rounded-full font-display text-3xl', 'bg-navy-950 text-brass-300' => $loop->even === false, 'bg-brass-400 text-navy-950' => $loop->even]) aria-hidden="true">{{ $member->initials }}</span>
                        <h3 class="mt-5 font-display text-2xl text-navy-950">{{ $member->name }}</h3>
                        <p class="text-sm font-semibold text-brass-600">{{ $member->role }}</p>
                        <p class="mt-3 text-sm text-navy-500">{{ $member->bio }}</p>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Harbors --}}
    <section class="bg-white py-24 md:py-32">
        <div class="container">
            <x-section-heading eyebrow="Where we sail from" center :tight="false" class="reveal mx-auto max-w-2xl">Four Chicago harbors</x-section-heading>
            <div class="mt-14 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($harbors as $harbor)
                    <a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($harbor->map_query) }}" target="_blank" rel="noopener noreferrer" class="reveal group overflow-hidden rounded-3xl bg-shell ring-1 ring-navy-100 transition hover:-translate-y-1 hover:shadow-card" @if ($loop->index) data-delay="{{ $loop->index }}" @endif>
                        <img src="{{ asset($harbor->image) }}" alt="{{ $harbor->name }}" loading="lazy" class="h-44 w-full object-cover">
                        <div class="p-6">
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-brass-600">{{ $harbor->area }}</p>
                            <h3 class="mt-1 font-display text-2xl text-navy-950">{{ $harbor->name }}</h3>
                            <p class="mt-2 text-sm text-navy-500">{{ $harbor->notes }}</p>
                            <p class="mt-4 inline-flex items-center gap-1.5 text-sm font-bold text-navy-900 group-hover:text-brass-600"><i data-lucide="navigation" class="h-4 w-4"></i>Get directions<span class="sr-only"> to {{ $harbor->name }} (opens Google Maps)</span></p>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    <section class="px-4 py-24">
        <div class="reveal relative isolate mx-auto max-w-7xl overflow-hidden rounded-[2.5rem] bg-navy-950 px-6 py-20 text-center text-white">
            <img src="{{ asset('images/chicago-river-night.jpg') }}" alt="" loading="lazy" class="absolute inset-0 -z-10 h-full w-full object-cover opacity-35">
            <h2 class="mx-auto max-w-3xl font-display text-4xl leading-tight md:text-6xl md:leading-none">Come sail with us</h2>
            <p class="mx-auto mt-4 max-w-xl text-white/70">Your captain is ready. Choose your yacht and experience in minutes.</p>
            <div class="mt-10 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ route('booking.create') }}" class="inline-flex items-center gap-2 rounded-full bg-brass-400 px-8 py-4 text-sm font-bold text-navy-950 shadow-glow hover:bg-brass-300">Book Now <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                <a href="{{ route('contact') }}" class="inline-flex items-center gap-2 rounded-full border border-white/30 px-8 py-4 text-sm font-bold hover:bg-white hover:text-navy-950">Contact Us</a>
            </div>
        </div>
    </section>
@endsection
