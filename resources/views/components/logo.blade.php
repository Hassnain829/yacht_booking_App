{{-- Brand mark + wordmark (mockup layout.js logo()). size: sm (wizard header) | md | lg --}}
@props(['size' => 'md'])

<a href="{{ route('home') }}" {{ $attributes->class('group flex shrink-0 items-center gap-3 whitespace-nowrap') }} aria-label="{{ config('site.business.name') }} home">
    <span @class([
        'grid place-items-center rounded-full border border-brass-300/50 bg-white/5 text-brass-300 transition group-hover:bg-brass-400 group-hover:text-navy-950',
        'h-12 w-12' => $size === 'lg',
        'h-11 w-11' => $size === 'md',
        'h-10 w-10' => $size === 'sm',
    ])>
        <i data-lucide="anchor" class="h-5 w-5"></i>
    </span>
    <span class="leading-none">
        <span @class(['block font-extrabold tracking-[0.42em] text-brass-300', 'text-[9px]' => $size === 'sm', 'text-[10px]' => $size !== 'sm'])>CHICAGO</span>
        <span @class(['mt-1 block font-display text-white', 'text-lg' => $size === 'sm', 'text-[1.35rem]' => $size !== 'sm'])>Yacht Booking</span>
    </span>
</a>
