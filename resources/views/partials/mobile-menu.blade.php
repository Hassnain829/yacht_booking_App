{{-- Slide-in mobile navigation (below xl). Opened by #menu-open; behaviour in resources/js/ui/mobile-menu.js. --}}
@php($business = config('site.business'))

<div id="mobile-menu" class="fixed inset-0 z-[60] xl:hidden" aria-hidden="true" inert>
    <div data-close class="absolute inset-0 bg-navy-950/60 backdrop-blur-xs"></div>
    <aside class="drawer absolute inset-y-0 right-0 flex w-full max-w-sm flex-col overflow-y-auto bg-navy-950 p-6 text-white shadow-2xl"
           role="dialog" aria-modal="true" aria-label="Menu">
        <div class="flex items-center justify-between">
            <x-logo />
            <button type="button" data-close data-menu-focus class="grid h-11 w-11 place-items-center rounded-full bg-white/10 hover:bg-white/20" aria-label="Close menu">
                <i data-lucide="x" class="h-5 w-5"></i>
            </button>
        </div>

        <nav class="mt-10 flex flex-col" aria-label="Mobile">
            @foreach (config('site.nav') as $item)
                @php($active = request()->routeIs(...$item['active']))
                <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                   @class([
                       'flex items-center justify-between border-b border-white/10 py-3.5 font-display text-2xl',
                       'text-brass-300' => $active,
                       'text-white' => ! $active,
                   ])>{{ $item['label'] }}<i data-lucide="arrow-up-right" class="h-5 w-5 text-white/40"></i></a>
            @endforeach
        </nav>

        @php($account = $headerAccount ?? null)
        <div class="mt-8 rounded-2xl bg-white/5 p-4 ring-1 ring-white/10">
            @if ($account)
                <div class="flex items-center gap-3">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brass-400 text-sm font-extrabold text-navy-950">{{ $account->initials }}</span>
                    <span class="min-w-0"><span class="block truncate text-sm font-bold">{{ $account->name }}</span><span class="block truncate text-xs text-white/50">{{ $account->email }}</span></span>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2 text-sm font-semibold">
                    <a href="{{ route('account.trips.index') }}" class="rounded-xl bg-white/5 px-3 py-2.5 text-center hover:bg-white/10">My trips</a>
                    @if ($account->role === 'owner')
                        <a href="{{ route('owner.dashboard') }}" class="rounded-xl bg-white/5 px-3 py-2.5 text-center hover:bg-white/10">Owner dashboard</a>
                    @else
                        <a href="{{ route('account.profile') }}" class="rounded-xl bg-white/5 px-3 py-2.5 text-center hover:bg-white/10">Profile</a>
                    @endif
                </div>
            @else
                <p class="text-sm font-bold">Your account</p>
                <p class="mt-1 text-xs text-white/50">Trips, receipts and your boats in one place.</p>
                <div class="mt-4 grid grid-cols-2 gap-2 text-sm font-bold">
                    <a href="{{ route('login') }}" class="rounded-full bg-white px-3 py-2.5 text-center text-navy-950 hover:bg-brass-300">Log in</a>
                    <a href="{{ route('register') }}" class="rounded-full border border-white/20 px-3 py-2.5 text-center hover:bg-white/10">Sign up</a>
                </div>
            @endif
        </div>

        <div class="mt-auto space-y-3 pt-8">
            <x-button :href="route('booking.create')" icon="arrow-right" class="w-full">Book Now</x-button>
            <a href="tel:{{ $business['phone_href'] }}" class="flex w-full items-center justify-center gap-2 rounded-full border border-white/20 py-4 text-sm font-bold">
                <i data-lucide="phone" class="h-4 w-4"></i>{{ $business['phone'] }}
            </a>
        </div>
    </aside>
</div>
