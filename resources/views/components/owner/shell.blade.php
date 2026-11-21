{{--
    Owner area frame (mockup owner-dashboard.html / my-boats.html): pill nav, eyebrow + heading, page actions.

    <x-owner.shell active="boats" heading="My boats">
        <x-slot:actions> … </x-slot:actions>
        …page content…
    </x-owner.shell>

    The nav scrolls sideways on small screens (7 sections); the active pill is scrolled into view by ui/owner-nav.js.
--}}
@props(['active', 'heading', 'intro' => null])

@php
    $items = [
        'dashboard' => ['Dashboard', route('owner.dashboard')],
        'boats' => ['My boats', route('owner.boats.index')],
        'bookings' => ['Bookings', route('owner.bookings.index')],
        'calendar' => ['Calendar', route('owner.calendar')],
        'earnings' => ['Earnings', route('owner.earnings')],
        'payouts' => ['Payouts', route('owner.payouts.index')],
        'complaints' => ['Complaints', route('owner.complaints.index')],
    ];
@endphp

<div class="container max-w-5xl">
    <div class="relative mt-6 overflow-x-auto no-scrollbar" data-owner-nav>
        <nav class="flex w-max min-w-full gap-1 rounded-full bg-white p-1 text-sm font-bold shadow-xs ring-1 ring-navy-100 sm:min-w-0" aria-label="Owner">
            @foreach ($items as $key => [$label, $url])
                <a href="{{ $url }}" @class([
                    'flex-1 whitespace-nowrap rounded-full px-5 py-2.5 text-center',
                    'bg-navy-950 text-white' => $active === $key,
                    'text-navy-500 hover:text-navy-950' => $active !== $key,
                ]) @if ($active === $key) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mt-10 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
        <div>
            <p class="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.3em] text-brass-600"><span class="h-px w-10 bg-brass-400"></span>Owner</p>
            <h1 class="mt-3 font-display text-4xl text-navy-950 md:text-5xl">{{ $heading }}</h1>
            @if ($intro)
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-navy-500">{{ $intro }}</p>
            @endif
        </div>
        {{ $actions ?? '' }}
    </div>

    {{ $slot }}
</div>
