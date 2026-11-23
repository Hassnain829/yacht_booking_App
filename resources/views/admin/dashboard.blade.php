@extends('layouts.admin', ['title' => 'Dashboard', 'active' => 'dashboard'])

@section('content')
    <x-admin.page-header title="Good {{ $now->hour < 12 ? 'morning' : ($now->hour < 18 ? 'afternoon' : 'evening') }}, {{ auth()->user()->first_name }}" description="{{ $now->format('l, F j') }} · Chicago time. Here's what needs your attention.">
        <x-slot:actions>
            <x-button :href="route('admin.bookings.index')" variant="dark" icon-left="calendar-check">All bookings</x-button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mt-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-admin.stat label="Trips today" :value="$stats['today']" note="{{ $stats['next7'] }} more in the next 7 days" icon="sailboat" tone="dark" />
        <x-admin.stat label="Revenue this month" :value="money($stats['month_gross'], 'never')" note="Gross, before refunds" icon="trending-up" />
        <x-admin.stat label="Commission this month" :value="money($stats['month_commission'], 'never')" note="From owner boats" icon="percent" />
        <x-admin.stat label="Bookings" :value="array_sum($stats['by_status'])" note="{{ $stats['by_status']['confirmed'] }} confirmed · {{ $stats['by_status']['completed'] }} completed · {{ $stats['by_status']['cancelled'] }} cancelled" icon="calendar-check" />
    </div>

    <p class="mt-2 text-xs text-navy-400">Trips, revenue, withdrawals and the action list are demo data until the booking engine (Phase 4) and finance (Phases 5–7).</p>

    {{-- Real data since 3.4.7 --}}
    <x-admin.card title="Fleet & catalog" class="mt-6">
        <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['sailboat', 'Live boats', $fleet['live'], route('admin.boats.index', ['status' => 'published']), false],
                ['eye-off', 'Shown as Unavailable', $fleet['unavailable'], route('admin.boats.index', ['bookable' => 'no']), false],
                ['pause', 'Hidden or draft', $fleet['hidden'], route('admin.boats.index', ['status' => 'hidden']), false],
                ['clipboard-check', 'Owner boats in review', $fleet['review'], route('admin.boats.index', ['status' => 'review']), $fleet['review'] > 0],
                ['sparkles', 'Experiences offered', $fleet['experiences'], route('admin.experiences.index'), false],
                ['ship-wheel', 'Active captains', $fleet['captains'], route('admin.captains.index', ['status' => 'active']), false],
                ['triangle-alert', 'Licenses expiring (30 days)', $fleet['licenses'], route('admin.captains.index', ['license' => 'expiring']), $fleet['licenses'] > 0],
            ] as [$icon, $label, $count, $url, $warn])
                <li>
                    <a href="{{ $url }}" @class(['flex items-center gap-3 rounded-xl p-3 ring-1 transition hover:-translate-y-0.5', 'bg-amber-50 ring-amber-200' => $warn, 'bg-navy-50/50 ring-navy-100' => ! $warn])>
                        <span @class(['grid h-9 w-9 shrink-0 place-items-center rounded-xl', 'bg-amber-400 text-navy-950' => $warn, 'bg-white text-navy-500' => ! $warn])><i data-lucide="{{ $icon }}" class="h-4 w-4"></i></span>
                        <span class="min-w-0"><span class="block text-lg font-extrabold text-navy-950">{{ $count }}</span><span class="block text-xs font-semibold leading-tight text-navy-500">{{ $label }}</span></span>
                    </a>
                </li>
            @endforeach
        </ul>
    </x-admin.card>

    <div class="mt-6 grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
        <div class="min-w-0 space-y-6">
            <x-admin.card title="Needs attention">
                <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($stats['actions'] as [$icon, $label, $count, $url])
                        <li>
                            <a href="{{ $url }}" @class([
                                'flex items-center gap-3 rounded-xl p-4 ring-1 transition hover:-translate-y-0.5',
                                'bg-amber-50 ring-amber-200' => $count > 0,
                                'bg-navy-50/50 ring-navy-100' => $count === 0,
                            ])>
                                <span @class(['grid h-10 w-10 shrink-0 place-items-center rounded-xl', 'bg-amber-400 text-navy-950' => $count > 0, 'bg-white text-navy-300' => $count === 0])><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
                                <span class="min-w-0"><span class="block text-xl font-extrabold text-navy-950">{{ $count }}</span><span class="block text-xs font-semibold leading-tight text-navy-500">{{ $label }}</span></span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </x-admin.card>

            <x-admin.card title="Next trips" flush>
                <x-slot:actions><a href="{{ route('admin.bookings.index') }}" class="text-sm font-bold text-brass-600 hover:text-brass-700">View all</a></x-slot:actions>
                <x-admin.table>
                    <x-slot:head><th scope="col">When</th><th scope="col">Trip</th><th scope="col">Guest</th><th scope="col">Captain</th><th scope="col" class="text-right">Total</th></x-slot:head>
                    @foreach ($upcoming as $b)
                        <tr>
                            <td><a href="{{ route('admin.bookings.show', $b->ref) }}" class="font-bold text-navy-950 hover:underline">{{ $b->starts_at->format('D, M j') }}</a><span class="block text-xs text-navy-400">{{ $b->starts_at->format('g:i A') }} · {{ $b->ref }}</span></td>
                            <td><span class="font-semibold text-navy-900">{{ $b->boat }}</span><span class="block text-xs text-navy-400">{{ $b->experience }} · {{ $b->guests }} guests</span></td>
                            <td>{{ $b->customer }}</td>
                            <td>@if ($b->captain){{ $b->captain }}@else<x-admin.pill tone="warning">Not assigned</x-admin.pill>@endif</td>
                            <td class="text-right font-bold text-navy-950">{{ money($b->total_cents, 'never') }}</td>
                        </tr>
                    @endforeach
                </x-admin.table>
            </x-admin.card>
        </div>

        <div class="min-w-0 space-y-6">
            <x-admin.card title="Withdrawals waiting">
                <ul class="space-y-3">
                    @foreach ($withdrawals as $w)
                        <li class="flex items-center justify-between gap-3">
                            <div class="min-w-0"><p class="truncate text-sm font-bold text-navy-950">{{ $w->owner }}</p><p class="text-xs text-navy-400">{{ $w->ref }} · {{ $w->requested_at->diffForHumans() }}</p></div>
                            <div class="text-right"><p class="text-sm font-extrabold text-navy-950">{{ money($w->amount_cents, 'always') }}</p><x-admin.pill :tone="$w->status === 'requested' ? 'warning' : 'muted'">{{ $w->status === 'requested' ? 'To approve' : 'On hold' }}</x-admin.pill></div>
                        </li>
                    @endforeach
                </ul>
                <a href="{{ route('admin.payouts.index') }}" class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-brass-600 hover:text-brass-700">Review payouts <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
            </x-admin.card>

            <x-admin.card title="Recent activity">
                @if ($activity->isEmpty())
                    <p class="text-sm text-navy-500">Nothing recorded yet.</p>
                @else
                    <ol class="space-y-4">
                        @foreach ($activity as $entry)
                            <li class="flex gap-3">
                                <span @class(['mt-1 h-2 w-2 shrink-0 rounded-full', 'bg-navy-300' => ! $entry->actor, 'bg-brass-400' => $entry->actor])></span>
                                <div class="min-w-0"><p class="text-sm font-semibold text-navy-900">{{ $entry->action }}</p>@if ($entry->reason)<p class="truncate text-xs text-navy-500">{{ $entry->reason }}</p>@endif<p class="text-[11px] text-navy-400">{{ $entry->actor?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans() }}</p></div>
                            </li>
                        @endforeach
                    </ol>
                @endif
                <a href="{{ route('admin.audit.index') }}" class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-brass-600 hover:text-brass-700">Full audit log <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
            </x-admin.card>
        </div>
    </div>
@endsection
