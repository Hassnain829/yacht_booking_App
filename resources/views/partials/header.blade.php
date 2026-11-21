{{--
    Fixed site header + promo strip (mockup layout.js). Transparent over dark heroes, solid
    navy after 40px of scroll (resources/js/ui/header.js), or always solid when $solid is true.
--}}
@php
    $business = config('site.business');
    $promo = config('site.promo');
    $showPromo = $promo['enabled'] && request()->cookie('promo_hidden') !== $promo['id'];
@endphp

<header id="header" data-solid="{{ $solid ? 'true' : 'false' }}" @class(['fixed inset-x-0 top-0 z-50 transition-all duration-300', 'is-solid' => $solid])>
    @if ($showPromo)
        <div id="promo-bar" data-promo-id="{{ $promo['id'] }}" class="relative bg-brass-400 text-navy-950">
            <div class="container flex items-center justify-center gap-3 py-2 pr-10 text-center text-xs font-semibold sm:text-sm">
                <i data-lucide="sparkles" class="hidden h-4 w-4 sm:block"></i>
                <span>{{ $promo['text'] }} <strong class="tracking-wider">{{ $promo['code'] }}</strong></span>
                <a href="{{ route('booking.create') }}" class="hidden rounded-full bg-navy-950 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-white transition hover:bg-navy-800 md:inline-block">{{ $promo['cta'] }}</a>
            </div>
            <button type="button" id="promo-close" class="absolute right-3 top-1/2 grid h-7 w-7 -translate-y-1/2 place-items-center rounded-full hover:bg-navy-950/10" aria-label="Dismiss offer">
                <i data-lucide="x" class="h-4 w-4"></i>
            </button>
        </div>
    @endif

    <div class="container flex h-20 items-center justify-between gap-6">
        <x-logo />

        <nav class="hidden items-center gap-7 xl:flex" aria-label="Main">
            @foreach (config('site.nav') as $item)
                @php($active = request()->routeIs(...$item['active']))
                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                   @class([
                       'nav-link whitespace-nowrap text-[13px] font-semibold uppercase tracking-[0.12em]',
                       'is-active text-white' => $active,
                       'text-white/75 hover:text-white' => ! $active,
                   ])>{{ $item['label'] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center gap-3">
            <a href="tel:{{ $business['phone_href'] }}" class="hidden h-10 w-10 place-items-center rounded-full bg-white/10 text-white/80 transition hover:bg-white/20 hover:text-white min-[1360px]:grid"
               aria-label="Call {{ $business['phone'] }}" title="{{ $business['phone'] }}">
                <i data-lucide="phone" class="h-4 w-4"></i>
            </a>
            @include('partials.account-menu')
            <x-button :href="route('booking.create')" icon="arrow-right" class="hidden whitespace-nowrap sm:inline-flex">Book Now</x-button>
            <button type="button" id="menu-open" class="grid h-11 w-11 place-items-center rounded-full bg-white/10 text-white transition hover:bg-white/20 xl:hidden"
                    aria-label="Open menu" aria-controls="mobile-menu" aria-expanded="false">
                <i data-lucide="menu" class="h-5 w-5"></i>
            </button>
        </div>
    </div>
</header>

@include('partials.mobile-menu')
