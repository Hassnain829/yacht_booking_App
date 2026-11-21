{{--
    Admin layout: dark sidebar (drawer on small screens), sticky top bar with search, content area.
    @extends('layouts.admin', ['title' => 'Bookings', 'active' => 'bookings', 'page' => 'admin-bookings'])
    Phase 2: behind auth + 2FA + the admin role. Every page is noindex.
--}}
@php
    $admin = app(\App\Support\Demo\DemoAdmin::class);
    $me = $admin->admin();
    $counts = collect($admin->stats()['actions'])->mapWithKeys(fn ($a) => [$a[1] => $a[2]]);
    $groups = [
        'Overview' => [
            ['dashboard', 'layout-dashboard', 'Dashboard', route('admin.dashboard'), null],
        ],
        'Operations' => [
            ['bookings', 'calendar-check', 'Bookings', route('admin.bookings.index'), $counts['Trips without a captain'] ?: null],
            ['captains', 'ship-wheel', 'Captains', route('admin.captains.index'), null],
            ['complaints', 'message-square-warning', 'Complaints', route('admin.complaints.index'), $counts['Open complaints'] ?: null],
            ['inbox', 'inbox', 'Inbox', route('admin.inbox.index'), $counts['New messages'] ?: null],
        ],
        'Fleet' => [
            ['boats', 'sailboat', 'Boats', route('admin.boats.index'), null],
            ['listings', 'clipboard-check', 'Listing reviews', route('admin.listings.index'), $counts['Listings to review'] ?: null],
            ['catalog', 'sparkles', 'Experiences & add-ons', route('admin.catalog'), null],
        ],
        'Finance' => [
            ['payouts', 'landmark', 'Payouts', route('admin.payouts.index'), $counts['Withdrawals to approve'] ?: null],
            ['payments', 'credit-card', 'Payments', route('admin.payments.index'), $counts['Failed charges'] ?: null],
        ],
        'People' => [
            ['users', 'users', 'Users', route('admin.users.index'), null],
        ],
        'System' => [
            ['settings', 'settings', 'Settings', route('admin.settings'), null],
            ['audit', 'scroll-text', 'Audit log', route('admin.audit.index'), null],
        ],
    ];
    $title = ($title ?? 'Admin').' · Admin';
@endphp
<!DOCTYPE html>
<html lang="en" class="js">
@include('partials.head', ['title' => $title, 'robots' => 'noindex, nofollow'])
<body class="bg-shell font-sans text-navy-900 antialiased" data-page="{{ $page ?? 'admin' }}">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[200] focus:rounded-full focus:bg-brass-400 focus:px-5 focus:py-3 focus:text-sm focus:font-bold focus:text-navy-950">Skip to content</a>

    <aside id="admin-sidebar" class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col bg-navy-950 text-white transition-transform duration-300 lg:translate-x-0 [&.is-open]:translate-x-0" aria-label="Admin navigation">
        <div class="flex h-16 shrink-0 items-center justify-between gap-3 border-b border-white/10 px-5">
            <x-logo size="sm" />
            <span class="rounded-md bg-brass-400 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-navy-950">Admin</span>
        </div>
        <nav class="flex-1 overflow-y-auto px-3 py-4">
            @foreach ($groups as $group => $items)
                <p class="px-3 pb-1.5 pt-3.5 text-[10px] font-bold uppercase tracking-[0.25em] text-white/40 first:pt-0">{{ $group }}</p>
                <ul class="space-y-0.5">
                    @foreach ($items as [$key, $icon, $label, $url, $badge])
                        <li>
                            <a href="{{ $url }}" @class([
                                'flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold transition',
                                'bg-white/10 text-white' => ($active ?? '') === $key,
                                'text-white/65 hover:bg-white/5 hover:text-white' => ($active ?? '') !== $key,
                            ]) @if (($active ?? '') === $key) aria-current="page" @endif>
                                <i data-lucide="{{ $icon }}" @class(['h-4 w-4 shrink-0', 'text-brass-300' => ($active ?? '') === $key])></i>
                                <span class="flex-1">{{ $label }}</span>
                                @if ($badge)
                                    <span class="rounded-full bg-brass-400 px-2 py-0.5 text-[10px] font-extrabold text-navy-950">{{ $badge }}</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>
        <div class="shrink-0 border-t border-white/10 p-3">
            <a href="{{ route('home') }}" class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold text-white/65 hover:bg-white/5 hover:text-white"><i data-lucide="external-link" class="h-4 w-4"></i>View the website</a>
        </div>
    </aside>
    <div class="fixed inset-0 z-40 hidden bg-navy-950/60 backdrop-blur-xs lg:hidden" data-admin-overlay></div>

    <div class="min-h-screen lg:pl-64">
        <header class="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-navy-100 bg-white/90 px-4 backdrop-blur md:gap-4 md:px-8">
            <button type="button" data-admin-menu aria-controls="admin-sidebar" aria-expanded="false" class="grid h-10 w-10 place-items-center rounded-xl ring-1 ring-navy-100 hover:bg-navy-50 lg:hidden" aria-label="Open menu"><i data-lucide="menu" class="h-5 w-5"></i></button>
            <form method="GET" action="{{ route('admin.bookings.index') }}" class="relative hidden max-w-md flex-1 sm:block" role="search">
                <label for="admin-search" class="sr-only">Search bookings</label>
                <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                <input id="admin-search" name="q" type="search" maxlength="60" value="{{ is_string(request('q')) ? request('q') : '' }}" placeholder="Search bookings by reference or guest…" class="w-full rounded-xl border border-navy-200 bg-white py-2.5 pl-10 pr-4 text-sm focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100">
            </form>
            <div class="ml-auto flex items-center gap-2 md:gap-3">
                <button type="button" data-soon class="relative grid h-10 w-10 place-items-center rounded-xl ring-1 ring-navy-100 hover:bg-navy-50" aria-label="Notifications"><i data-lucide="bell" class="h-5 w-5 text-navy-600"></i><span class="absolute right-2 top-2 h-2 w-2 rounded-full bg-red-500"></span></button>
                <div class="flex items-center gap-2.5">
                    <span class="grid h-10 w-10 place-items-center rounded-full bg-navy-950 text-sm font-bold text-brass-300">{{ $me->initials }}</span>
                    <span class="hidden text-sm leading-tight md:block"><span class="block font-bold text-navy-950">{{ $me->name }}</span><span class="text-xs text-navy-400">Administrator</span></span>
                </div>
            </div>
        </header>

        <main id="main" tabindex="-1" class="mx-auto max-w-[90rem] p-4 outline-hidden md:p-8">
            @yield('content')
        </main>
    </div>

    @stack('modals')
</body>
</html>
