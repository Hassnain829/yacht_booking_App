{{--
    Dark photo hero at the top of inner pages (mockup about.html), with breadcrumb.
    The transparent header sits on top of it.

    <x-page-hero image="images/chicago-navy-pier.jpg" crumb="About Us" intro="We started with one boat…">
        Chicago boaters, <em>at your service</em>
    </x-page-hero>

    Slot `after` renders extra content under the intro (buttons, search, stats).
--}}
@props([
    'image',
    'crumb',
    'intro' => null,
    // Per-page tuning, as in the mockup (full class names so Tailwind can see them):
    'imageClass' => 'opacity-55',
    'overlayClass' => 'from-navy-950/80 via-navy-950/40 to-navy-950',
])

<section {{ $attributes->class('relative isolate overflow-hidden bg-navy-950 pb-20 pt-44 md:pb-28 md:pt-52') }}>
    <img src="{{ asset($image) }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover {{ $imageClass }}" fetchpriority="high">
    <div class="absolute inset-0 -z-10 bg-gradient-to-b {{ $overlayClass }}" aria-hidden="true"></div>
    <div class="container text-center text-white">
        <nav class="reveal flex justify-center gap-2 text-[11px] font-bold uppercase tracking-[0.25em] text-white/50" aria-label="Breadcrumb">
            <a href="{{ route('home') }}" class="hover:text-white">Home</a>
            <span aria-hidden="true">/</span>
            <span class="text-brass-300" aria-current="page">{{ $crumb }}</span>
        </nav>
        <h1 class="reveal mt-5 font-display text-5xl md:text-7xl [&_em]:text-brass-300" data-delay="1">{{ $slot }}</h1>
        @if ($intro)
            <p class="reveal mx-auto mt-5 max-w-2xl text-lg text-white/70" data-delay="2">{{ $intro }}</p>
        @endif
        {{ $after ?? '' }}
    </div>
</section>
