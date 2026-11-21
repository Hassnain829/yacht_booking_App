{{--
    Header account button + dropdown (ui/account-menu.js). $headerAccount comes from HeaderComposer:
    null = guest (log in / create account), otherwise the signed-in user's initials and links for their role.
--}}
@php
    $account = $headerAccount ?? null;
    $link = 'flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-navy-700 transition hover:bg-navy-50 hover:text-navy-950';
    $links = $account ? array_filter([
        ['calendar-check', 'My trips', route('account.trips.index')],
        ['user-round', 'Profile & security', route('account.profile')],
        $account->role === 'owner' ? ['layout-dashboard', 'Owner dashboard', route('owner.dashboard')] : null,
        $account->role === 'owner' ? ['sailboat', 'My boats', route('owner.boats.index')] : null,
        $account->role === 'owner' ? ['wallet', 'Earnings & payouts', route('owner.earnings')] : null,
        $account->role !== 'owner' ? ['sailboat', 'List your boat', route('list-your-boat')] : null,
    ]) : [
        ['calendar-check', 'My trips', route('account.trips.index')],
        ['sailboat', 'List your boat', route('list-your-boat')],
        ['life-buoy', 'Help & contact', route('contact')],
    ];
@endphp

<div class="relative" data-account-menu>
    <button type="button" data-account-toggle aria-expanded="false" aria-controls="account-panel"
            aria-label="{{ $account ? 'Account menu — '.$account->name : 'Log in or create an account' }}"
            @class([
                'grid h-10 w-10 place-items-center rounded-full transition',
                'bg-brass-400 text-sm font-extrabold text-navy-950 ring-2 ring-white/25 hover:bg-brass-300' => $account,
                'bg-white/10 text-white hover:bg-white/20' => ! $account,
            ])>
        @if ($account)
            {{ $account->initials }}
        @else
            <i data-lucide="user-round" class="h-5 w-5"></i>
        @endif
    </button>

    <div id="account-panel" hidden class="account-panel absolute right-0 top-full mt-3 w-72 max-w-[calc(100vw-2rem)] overflow-hidden rounded-2xl bg-white text-navy-900 shadow-2xl ring-1 ring-navy-950/5">
        @if ($account)
            <div class="flex items-center gap-3 border-b border-navy-100 p-4">
                <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-navy-950 text-sm font-extrabold text-brass-300">{{ $account->initials }}</span>
                <span class="min-w-0">
                    <span class="block truncate text-sm font-bold text-navy-950">{{ $account->name }}</span>
                    <span class="block truncate text-xs text-navy-500">{{ $account->email }}</span>
                </span>
            </div>
        @else
            <div class="p-5">
                <p class="font-display text-xl text-navy-950">Welcome aboard</p>
                <p class="mt-1 text-xs leading-relaxed text-navy-500">Log in to see your trips, receipts and saved cards — or to manage your boats.</p>
                <x-button :href="route('login')" variant="dark" class="mt-4 w-full">Log in</x-button>
                <x-button :href="route('register')" variant="outline" class="mt-2 w-full">Create an account</x-button>
            </div>
        @endif

        <nav class="border-t border-navy-100 p-2" aria-label="Account">
            @foreach ($links as [$icon, $label, $url])
                <a href="{{ $url }}" class="{{ $link }}"><i data-lucide="{{ $icon }}" class="h-4 w-4 text-brass-500"></i>{{ $label }}</a>
            @endforeach
        </nav>

        @if ($account)
            <form method="POST" action="{{ url('/logout') }}" class="border-t border-navy-100 p-2" data-demo-form="Demo: logging out goes live in Phase 2.">
                @csrf
                <button type="submit" class="{{ $link }} w-full text-left"><i data-lucide="log-out" class="h-4 w-4 text-navy-400"></i>Log out</button>
            </form>
        @endif
    </div>
</div>
