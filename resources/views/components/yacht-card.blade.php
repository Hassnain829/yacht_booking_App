{{--
    Fleet card (mockup CYB.ui.yachtCard) + the "Unavailable" state (PLAN D12).

    <x-yacht-card :yacht="$yacht" />
    <x-yacht-card :yacht="$yacht" experience="sunset" :delay="2" />

    $yacht: slug, title, category, length_ft, max_guests, min_hours, hourly_rate_cents,
            rating_avg, badge, cover, short, booking_enabled, harbor->name
    Unavailable boats stay visible (details link works) but cannot be booked.
--}}
@props(['yacht', 'experience' => null, 'delay' => null])

@php
    $available = (bool) $yacht->booking_enabled;
    $detailsUrl = route('yachts.show', $yacht->slug);
    $bookUrl = route('booking.create', array_filter(['yacht' => $yacht->slug, 'experience' => $experience]));
@endphp

<article {{ $attributes->class('reveal group flex flex-col overflow-hidden rounded-3xl bg-white shadow-card ring-1 ring-navy-100/60 transition duration-500 hover:-translate-y-1.5') }} @if ($delay) data-delay="{{ $delay }}" @endif>
    <a href="{{ $detailsUrl }}" class="relative block aspect-[4/3] overflow-hidden" tabindex="-1" aria-hidden="true">
        <img src="{{ asset($yacht->cover) }}" alt="" loading="lazy" decoding="async"
             @class(['h-full w-full object-cover transition duration-[1.2s] group-hover:scale-110', 'grayscale-[60%]' => ! $available])>
        <div class="absolute inset-0 bg-gradient-to-t from-navy-950/60 via-transparent to-transparent"></div>
        @if ($available)
            @if ($yacht->badge)
                <x-badge variant="light" class="absolute left-4 top-4">{{ $yacht->badge }}</x-badge>
            @endif
        @else
            <x-badge variant="unavailable" icon="calendar-x" class="absolute left-4 top-4">Unavailable</x-badge>
        @endif
        <span class="absolute right-4 top-4 inline-flex items-center gap-1 rounded-full bg-navy-950/70 px-2.5 py-1 text-xs font-bold text-white backdrop-blur">
            <i data-lucide="star" class="h-3.5 w-3.5 fill-brass-400 text-brass-400"></i>{{ number_format((float) $yacht->rating_avg, 1) }}
        </span>
        <span class="absolute bottom-4 left-4 inline-flex items-center gap-1.5 text-xs font-semibold text-white">
            <i data-lucide="ship-wheel" class="h-4 w-4 text-brass-300"></i>Captain included
        </span>
    </a>

    <div class="flex flex-1 flex-col p-6">
        <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-brass-600">{{ $yacht->category }} · {{ $yacht->length_ft }} ft</p>
        <h3 class="mt-1.5 font-display text-2xl text-navy-950">
            <a href="{{ $detailsUrl }}" class="hover:text-brass-600">{{ $yacht->title }}</a>
        </h3>
        <p class="mt-2 line-clamp-2 text-sm leading-relaxed text-navy-500">{{ $yacht->short }}</p>
        <div class="mt-4 flex flex-wrap gap-x-4 gap-y-2 text-xs font-semibold text-navy-600">
            <span class="inline-flex items-center gap-1.5"><i data-lucide="users" class="h-4 w-4 text-navy-400"></i>Up to {{ $yacht->max_guests }}</span>
            @if ($yacht->harbor)
                <span class="inline-flex items-center gap-1.5"><i data-lucide="map-pin" class="h-4 w-4 text-navy-400"></i>{{ $yacht->harbor->name }}</span>
            @endif
            <span class="inline-flex items-center gap-1.5"><i data-lucide="clock" class="h-4 w-4 text-navy-400"></i>{{ $yacht->min_hours }} hr min</span>
        </div>

        <div class="mt-auto pt-6">
            <div class="flex items-end justify-between gap-3 border-t border-navy-100 pt-5">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-navy-400">From</p>
                    <p class="text-2xl font-extrabold text-navy-950">{{ money($yacht->hourly_rate_cents) }}<span class="text-sm font-semibold text-navy-400">/hr</span></p>
                </div>
                <div class="flex gap-2">
                    <a href="{{ $detailsUrl }}" class="grid h-11 w-11 place-items-center rounded-full border border-navy-200 text-navy-700 transition hover:border-navy-950 hover:bg-navy-950 hover:text-white" aria-label="View {{ $yacht->title }} details">
                        <i data-lucide="arrow-up-right" class="h-4 w-4"></i>
                    </a>
                    @if ($available)
                        <a href="{{ $bookUrl }}" class="inline-flex h-11 items-center gap-2 rounded-full bg-navy-950 px-5 text-sm font-bold text-white transition hover:bg-brass-400 hover:text-navy-950">Book<span class="sr-only"> {{ $yacht->title }}</span></a>
                    @else
                        <span class="inline-flex h-11 cursor-not-allowed items-center gap-2 rounded-full bg-navy-100 px-5 text-sm font-bold text-navy-400" aria-disabled="true">Unavailable</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</article>
