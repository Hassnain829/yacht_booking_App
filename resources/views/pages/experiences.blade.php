@extends('layouts.app', [
    'title' => 'Experiences & Events',
    'description' => 'Sunset skyline cruises, Playpen swim parties, Navy Pier fireworks, river architecture tours, proposals and corporate charters — ready-made yacht experiences in Chicago.',
    'page' => 'experiences',
])

@php
    $chipOn = 'shrink-0 rounded-full bg-navy-950 px-5 py-2.5 text-sm font-bold text-white';
    $chipOff = 'shrink-0 rounded-full border border-navy-200 px-5 py-2.5 text-sm font-bold text-navy-700 hover:border-navy-950';
    $flipIndex = 0; // alternation counts visible rows only
@endphp

@section('content')
    <x-page-hero image="images/exp-party.jpg" crumb="Experiences" image-class="opacity-50"
                 intro="Chicago's favourite ways to spend a few hours on the water — each with its own route and departure times, planned by our captains. Pick one, choose your yacht and how many hours, and we handle the rest.">
        Experiences <em>&amp; Events</em>
    </x-page-hero>

    {{-- Category filter: plain links (work without JS); resources/js/pages/experiences.js filters instantly. --}}
    <section class="sticky top-20 z-30 border-b border-navy-100 bg-white/90 backdrop-blur-lg">
        <div class="container">
            <nav id="exp-filter" class="no-scrollbar -mx-1 flex gap-2 overflow-x-auto px-1 py-4" aria-label="Experience category" data-chip-on="{{ $chipOn }}" data-chip-off="{{ $chipOff }}">
                @foreach (App\Http\Controllers\ExperienceController::CATEGORIES as $value => $label)
                    <a href="{{ route('experiences.index', $value === 'all' ? [] : ['category' => $value]) }}" data-cat="{{ $value }}"
                       @if ($category === $value) aria-current="true" @endif class="{{ $category === $value ? $chipOn : $chipOff }}">{{ $label }}</a>
                @endforeach
            </nav>
        </div>
    </section>

    <section class="py-16 md:py-24">
        <div id="exp-list" class="container space-y-20 md:space-y-28">
            @foreach ($experiences as $experience)
                @php
                    $visible = $category === 'all' || $experience->category === $category;
                    $flip = $visible && $flipIndex++ % 2 === 1;
                @endphp
                <article id="{{ $experience->slug }}" data-cat="{{ $experience->category }}" class="grid scroll-mt-40 items-center gap-10 lg:grid-cols-2 lg:gap-20" @unless ($visible) hidden @endunless>
                    <div data-flip-media @class(['reveal relative', 'lg:order-2' => $flip])>
                        <div class="overflow-hidden rounded-[2rem]"><img src="{{ asset($experience->image) }}" alt="{{ $experience->name }}" loading="lazy" class="aspect-[5/4] w-full object-cover transition duration-[1.2s] hover:scale-105"></div>
                        <div data-flip-badge @class(['absolute -bottom-6 rounded-2xl bg-white p-5 shadow-card', 'left-6' => $flip, 'right-6' => ! $flip])>
                            <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Trips from</p>
                            @if ($experience->from_cents !== null)
                                <p class="text-2xl font-extrabold text-navy-950">{{ money($experience->from_cents, 'never') }}</p>
                                <p class="text-xs text-navy-400">incl. captain · before tax</p>
                            @else
                                <p class="text-2xl font-extrabold text-navy-950">On request</p>
                                <p class="text-xs text-navy-400">no yacht offers it right now</p>
                            @endif
                        </div>
                    </div>
                    <div class="reveal" data-delay="1">
                        <p class="inline-flex items-center gap-2 rounded-full bg-brass-100 px-3 py-1 text-[11px] font-bold uppercase tracking-[0.18em] text-brass-800"><i data-lucide="{{ $experience->icon }}" class="h-3.5 w-3.5"></i>{{ $experience->tagline }}</p>
                        <h2 class="mt-4 font-display text-4xl leading-tight text-navy-950 md:text-5xl md:leading-none">{{ $experience->name }}</h2>
                        <p class="mt-4 text-lg leading-relaxed text-navy-600">{{ $experience->description }}</p>
                        <ul class="mt-6 grid gap-2.5 sm:grid-cols-2">
                            @foreach ($experience->includes as $item)
                                <li class="flex items-center gap-2.5 text-sm font-semibold text-navy-700"><i data-lucide="circle-check" class="h-5 w-5 shrink-0 text-brass-500"></i>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <dl class="mt-7 grid grid-cols-3 divide-x divide-navy-100 rounded-2xl bg-white py-4 ring-1 ring-navy-100">
                            <div class="px-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Departs</dt><dd class="mt-1 text-sm font-bold text-navy-950">{{ $experience->departs_label ?: '—' }}</dd></div>
                            <div class="px-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Available</dt><dd class="mt-1 text-sm font-bold text-navy-950">{{ $experience->days_label }}</dd></div>
                            <div class="px-4"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Trip length</dt><dd class="mt-1 text-sm font-bold text-navy-950">Your choice</dd></div>
                        </dl>
                        <p class="mt-5 flex items-start gap-2 text-sm text-navy-600"><i data-lucide="{{ $experience->extras_label ? 'gift' : 'circle-check' }}" class="mt-0.5 h-4 w-4 shrink-0 text-brass-500"></i>
                            @if ($experience->extras_label)
                                <span>The experience is free: you pay for the yacht and its captain, by the hour. Optional extras at checkout: <strong class="text-navy-900">{{ $experience->extras_label }}</strong>.</span>
                            @else
                                <span>The experience is free: you pay only for the yacht and its captain, by the hour.</span>
                            @endif
                        </p>
                        @if ($experience->boats->isNotEmpty())
                            <div class="mt-6">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-navy-400">{{ trans_choice('{1} 1 yacht offers it|[2,*] :count yachts offer it', $experience->boats->count()) }}</p>
                                <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                                    @foreach ($experience->boats->take(4) as $boat)
                                        <li>
                                            <a href="{{ route('yachts.show', [$boat->slug, 'experience' => $experience->slug]) }}{{ $boat->booking_enabled ? '#book' : '' }}" class="group flex items-center gap-3 rounded-2xl bg-white p-2 pr-4 ring-1 ring-navy-100 transition hover:ring-brass-400">
                                                <img src="{{ asset($boat->cover_card ?? $boat->cover) }}" alt="" loading="lazy" @class(['h-12 w-16 shrink-0 rounded-xl object-cover', 'grayscale-[60%]' => ! $boat->booking_enabled])>
                                                <span class="min-w-0 flex-1">
                                                    <span class="block truncate text-sm font-bold text-navy-950 group-hover:text-brass-600">{{ $boat->title }}</span>
                                                    <span class="block text-xs text-navy-500">{{ $boat->booking_enabled ? 'Up to '.$boat->max_guests.' · from '.money($boat->hourly_rate_cents).'/hr' : 'Unavailable' }}</span>
                                                </span>
                                                <i data-lucide="arrow-right" class="h-4 w-4 shrink-0 text-navy-300 transition group-hover:translate-x-1 group-hover:text-brass-500"></i>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                        <div class="mt-8 flex flex-wrap gap-3">
                            <x-button :href="$experience->book_url" icon="arrow-right">Book this experience</x-button>
                            <x-button :href="route('yachts.index', ['experience' => $experience->slug])" variant="outline">See yachts</x-button>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <section class="px-4 pb-24">
        <div class="reveal mx-auto grid max-w-7xl overflow-hidden rounded-[2.5rem] bg-navy-950 text-white lg:grid-cols-2">
            <img src="{{ asset('images/exp-toast.jpg') }}" alt="Guests raising glasses on a yacht" loading="lazy" class="h-72 w-full object-cover lg:h-full">
            <div class="p-10 md:p-16">
                <p class="text-xs font-bold uppercase tracking-[0.3em] text-brass-300">Custom events</p>
                <h2 class="mt-4 font-display text-4xl leading-tight md:text-5xl md:leading-none">Planning something bigger?</h2>
                <p class="mt-4 text-white/65">Weddings, product launches, multi-yacht flotillas and brand activations — our events team builds a custom plan and quote within 24 hours.</p>
                <ul class="mt-6 space-y-3 text-sm text-white/80">
                    <li class="flex items-center gap-3"><i data-lucide="circle-check" class="h-5 w-5 text-brass-300"></i>Dedicated event coordinator</li>
                    <li class="flex items-center gap-3"><i data-lucide="circle-check" class="h-5 w-5 text-brass-300"></i>Catering, bar &amp; entertainment partners</li>
                    <li class="flex items-center gap-3"><i data-lucide="circle-check" class="h-5 w-5 text-brass-300"></i>Groups up to 250 across multiple yachts</li>
                </ul>
                <a href="{{ route('contact') }}" class="mt-8 inline-flex items-center gap-2 rounded-full bg-brass-400 px-7 py-3.5 text-sm font-bold text-navy-950 hover:bg-brass-300">Request a custom quote <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
            </div>
        </div>
    </section>
@endsection
