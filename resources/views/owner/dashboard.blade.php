@extends('layouts.app', [
    'title' => 'Owner Dashboard',
    'page' => 'owner',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@php
    // "Action required" cards (mockup owner-dashboard.js), most urgent first.
    $notices = [];
    if ($boats->isEmpty()) {
        $notices[] = ['Get started', "You haven't listed a boat yet", 'List your boat in about 15 minutes. Our team reviews every listing and helps you set the right Chicago pricing.', 'List your boat', route('list-your-boat', ['new' => 1])];
    }
    foreach ($openComplaints->whereNull('owner_response') as $complaint) {
        $notices[] = ['Action required', 'A guest reported a problem with a trip', $complaint->category_label.' · trip '.$complaint->booking->ref.' on '.$complaint->booking->starts_at->format('M j').'. Its earnings are on hold until our team reviews the report — add your side of the story.', 'Respond', route('owner.complaints.index').'#complaint-'.$complaint->ulid];
    }
    foreach ($drafts->take(1) as $draft) {
        $notices[] = ['Action required', 'Your boat listing is not submitted yet', '"'.$draft->display_title.'" is saved as a draft. Finish the remaining steps and submit it for review.', 'Continue listing', route('list-your-boat', ['listing' => $draft->ulid])];
    }
    if ($pending->isNotEmpty() && ! $profile->payout_account) {
        $notices[] = ['Action required', 'Your boat is pending review — add payout information', "Payout information is required for your boat to be visible to renters once it's approved. Add your bank details now so we can activate your listing right away.", 'Update payout info', route('owner.payouts.index').'#payout-method'];
    }

    $openCount = $openComplaints->count();
    $tiles = [
        ['sailboat', 'My boats'.($boats->count() ? ' ('.$boats->count().')' : ''), 'Manage the calendar, pricing, and other details of your boat(s).', route('owner.boats.index'), null],
        ['calendar-days', 'Calendar', 'See every trip and block dates when your boat is not available.', route('owner.calendar'), null],
        ['chart-no-axes-column', 'Earnings', 'What each trip earned after commission, and when it can be withdrawn.', route('owner.earnings'), null],
        ['landmark', 'Payout preferences', 'Add or edit your bank account details.', route('owner.payouts.index').'#payout-method', $profile->payout_account ? null : 'Incomplete'],
        ['history', 'Payout history', 'View your payout history.', route('owner.payouts.index').'#history', null],
        ['message-square-warning', 'Complaints', 'Read guest reports about a trip and send your response.', route('owner.complaints.index'), $openCount ? $openCount.' open' : null],
        ['user-round', 'Profile', 'View your profile page.', null, null],
        ['file-text', 'Tax forms', 'Update your tax information.', null, 'Incomplete'],
        ['user-plus', 'My boat manager', 'Invite someone to manage your bookings, messages, and boats.', null, null],
        ['life-buoy', 'Owner resources', 'Explore tools and tips to get the most out of Chicago Yacht Booking.', null, null],
    ];
@endphp

@section('content')
    <x-owner.shell active="dashboard" heading="Owner dashboard">
        <div class="mt-10">
            <div class="flex flex-col gap-8 border-b border-navy-100 pb-10 md:flex-row md:items-center">
                <div class="flex items-center gap-5">
                    <span class="relative grid h-20 w-20 shrink-0 place-items-center rounded-full bg-navy-950 font-display text-3xl text-brass-300">{{ $profile->initials }}
                        <span class="absolute -bottom-0.5 -right-0.5 grid h-7 w-7 place-items-center rounded-full bg-emerald-600 text-white ring-4 ring-shell" title="Verified owner"><i data-lucide="check" class="h-4 w-4"></i></span>
                    </span>
                    <div>
                        <p class="font-display text-2xl text-navy-950">{{ $profile->name }}</p>
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-navy-400">
                            @if ($profile->reviews_count)
                                {{ number_format($profile->rating_avg, 1) }} rating · {{ $profile->reviews_count }} reviews
                            @else
                                No reviews yet
                            @endif
                        </p>
                    </div>
                </div>
                <dl class="grid flex-1 grid-cols-3 divide-x divide-navy-100 rounded-3xl bg-white py-5 text-center shadow-xs ring-1 ring-navy-100 md:ml-auto md:max-w-lg">
                    <div class="flex flex-col-reverse px-3"><dt class="mt-1 text-xs text-navy-500">Avg. response rate</dt><dd class="text-xl font-extrabold text-navy-950">{{ $profile->response_rate ? $profile->response_rate.'%' : 'N/A' }}</dd></div>
                    <div class="flex flex-col-reverse px-3"><dt class="mt-1 text-xs text-navy-500">Completed bookings</dt><dd class="text-xl font-extrabold text-navy-950">{{ $completedCount }}</dd></div>
                    <div class="flex flex-col-reverse px-3"><dt class="mt-1 text-xs text-navy-500">Total earnings</dt><dd class="text-xl font-extrabold text-navy-950">{{ money($balances['earned'], 'always') }}</dd></div>
                </dl>
            </div>

            @if ($notices)
                <div class="mt-10 space-y-5">
                    @foreach ($notices as [$tag, $noticeTitle, $text, $cta, $href])
                        <div class="flex flex-col gap-5 rounded-3xl bg-white p-6 shadow-card ring-1 ring-brass-200 md:flex-row md:items-center md:p-7">
                            <div class="flex-1">
                                <span class="rounded-md bg-brass-400 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-navy-950">{{ $tag }}</span>
                                <h2 class="mt-3 text-lg font-extrabold text-navy-950">{{ $noticeTitle }}</h2>
                                <p class="mt-1 text-sm leading-relaxed text-navy-500">{{ $text }}</p>
                            </div>
                            <x-button :href="$href" size="lg" class="shrink-0 uppercase tracking-wider">{{ $cta }}</x-button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="mt-8 grid gap-5 md:grid-cols-2">
                @foreach ([
                    ['calendar-check', 'Bookings', 'You have '.$upcomingCount.' upcoming '.Str::plural('booking', $upcomingCount).'.', 'Go to bookings', route('owner.bookings.index')],
                    ['mail', 'Messages', 'You have 0 unread messages.', 'Go to messages', null],
                ] as [$icon, $cardTitle, $text, $cta, $href])
                    <div class="flex items-center gap-5 rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100">
                        <span class="grid h-12 w-12 shrink-0 place-items-center rounded-2xl bg-navy-950 text-brass-300"><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
                        <div class="min-w-0 flex-1"><h3 class="font-bold text-navy-950">{{ $cardTitle }}</h3><p class="mt-0.5 text-xs text-navy-500">{{ $text }}</p></div>
                        <a href="{{ $href ?? '#' }}" @if (! $href) data-soon @endif class="shrink-0 rounded-full bg-navy-950 px-4 py-2.5 text-[11px] font-bold uppercase tracking-wider text-white transition hover:bg-brass-400 hover:text-navy-950">{{ $cta }}</a>
                    </div>
                @endforeach
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($tiles as [$icon, $tileTitle, $text, $href, $flag])
                    <a href="{{ $href ?? '#' }}" @if (! $href) data-soon @endif class="group relative flex flex-col rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 transition hover:-translate-y-1 hover:shadow-card">
                        <div class="flex items-start justify-between gap-3">
                            <span class="grid h-11 w-11 place-items-center rounded-2xl bg-brass-100 text-brass-700 transition group-hover:bg-brass-400 group-hover:text-navy-950"><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
                            @if ($flag)
                                <span class="rounded-md bg-red-600 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-white">{{ $flag }}</span>
                            @endif
                        </div>
                        <h3 class="mt-5 font-bold text-navy-950">{{ $tileTitle }}</h3>
                        <p class="mt-1 text-sm text-navy-500">{{ $text }}</p>
                    </a>
                @endforeach
                <a href="{{ route('list-your-boat', ['new' => 1]) }}" class="flex flex-col justify-center rounded-3xl bg-navy-950 p-6 text-white transition hover:-translate-y-1 hover:shadow-card">
                    <i data-lucide="circle-plus" class="h-8 w-8 text-brass-300"></i>
                    <h3 class="mt-4 font-display text-2xl">List a new boat</h3>
                    <p class="mt-1 text-sm text-white/60">Add another boat to your fleet.</p>
                </a>
            </div>
        </div>
    </x-owner.shell>
@endsection
