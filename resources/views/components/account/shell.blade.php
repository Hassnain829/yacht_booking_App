{{--
    Customer account frame — same pill nav + heading pattern as the owner area (x-owner.shell).
    <x-account.shell active="trips" heading="My trips"> … </x-account.shell>
--}}
@props(['active', 'heading', 'intro' => null, 'back' => null])

@php
    $items = [
        'trips' => ['My trips', route('account.trips.index')],
        'profile' => ['Profile & security', route('account.profile')],
    ];
@endphp

<div class="container max-w-5xl">
    <div class="relative mt-6 overflow-x-auto no-scrollbar" data-owner-nav>
        <nav class="flex w-max min-w-full gap-1 rounded-full bg-white p-1 text-sm font-bold shadow-xs ring-1 ring-navy-100 sm:min-w-0" aria-label="Account">
            @foreach ($items as $key => [$label, $url])
                <a href="{{ $url }}" @class([
                    'flex-1 whitespace-nowrap rounded-full px-5 py-2.5 text-center',
                    'bg-navy-950 text-white' => $active === $key,
                    'text-navy-500 hover:text-navy-950' => $active !== $key,
                ]) @if ($active === $key && ! $back) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @if ($back)
        <a href="{{ $back[1] }}" class="mt-8 inline-flex items-center gap-1.5 text-sm font-bold text-navy-500 hover:text-navy-950"><i data-lucide="arrow-left" class="h-4 w-4"></i>{{ $back[0] }}</a>
    @endif

    <div @class(['flex flex-col justify-between gap-4 sm:flex-row sm:items-end', 'mt-10' => ! $back, 'mt-4' => $back])>
        <div>
            <p class="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.3em] text-brass-600"><span class="h-px w-10 bg-brass-400"></span>My account</p>
            <h1 class="mt-3 font-display text-4xl text-navy-950 md:text-5xl">{{ $heading }}</h1>
            @if ($intro)
                <p class="mt-3 max-w-2xl text-sm leading-relaxed text-navy-500">{{ $intro }}</p>
            @endif
        </div>
        {{ $actions ?? '' }}
    </div>

    {{ $slot }}
</div>
