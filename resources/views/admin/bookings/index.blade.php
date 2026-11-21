@extends('layouts.admin', ['title' => 'Bookings', 'active' => 'bookings'])

@php
    $select = 'rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
@endphp

@section('content')
    <x-admin.page-header title="Bookings" description="Every trip on house and owner boats. Search by reference, guest or boat." />

    <form method="GET" action="{{ route('admin.bookings.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-navy-100" role="search">
        <div class="relative min-w-[14rem] flex-1">
            <label for="filter-q" class="sr-only">Search</label>
            <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
            <input id="filter-q" name="q" type="search" maxlength="60" value="{{ $filters['q'] }}" placeholder="CYB-…, guest or boat" class="{{ $select }} w-full pl-10">
        </div>
        <label class="sr-only" for="filter-status">Status</label>
        <select id="filter-status" name="status" class="{{ $select }}">
            <option value="">All statuses</option>
            @foreach (\App\Support\Demo\DemoAdmin::BOOKING_STATUSES as $key => $s)
                <option value="{{ $key }}" @selected($filters['status'] === $key)>{{ $s['label'] }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="filter-kind">Boat type</label>
        <select id="filter-kind" name="kind" class="{{ $select }}">
            <option value="">House + owner boats</option>
            <option value="house" @selected($filters['kind'] === 'house')>House boats</option>
            <option value="owner" @selected($filters['kind'] === 'owner')>Owner boats</option>
        </select>
        <label class="sr-only" for="filter-captain">Captain</label>
        <select id="filter-captain" name="captain" class="{{ $select }}">
            <option value="">Any captain status</option>
            <option value="none" @selected($filters['captain'] === 'none')>Upcoming, no captain</option>
            <option value="assigned" @selected($filters['captain'] === 'assigned')>Captain assigned</option>
        </select>
        <x-button type="submit" variant="dark">Filter</x-button>
        @if (array_filter($filters))
            <a href="{{ route('admin.bookings.index') }}" class="px-2 py-2.5 text-sm font-bold text-navy-500 hover:text-navy-950">Clear</a>
        @endif
    </form>

    <x-admin.card flush class="mt-6">
        <x-admin.table>
            <x-slot:head>
                <x-admin.sort column="ref" label="Ref" :sort="$sort" :dir="$dir" />
                <x-admin.sort column="date" label="Date" :sort="$sort" :dir="$dir" />
                <th scope="col">Boat · experience</th>
                <th scope="col">Guest</th>
                <x-admin.sort column="guests" label="Guests" :sort="$sort" :dir="$dir" />
                <th scope="col">Captain</th>
                <th scope="col">Status</th>
                <x-admin.sort column="total" label="Total" :sort="$sort" :dir="$dir" />
            </x-slot:head>
            @forelse ($bookings as $b)
                <tr>
                    <td><a href="{{ route('admin.bookings.show', $b->ref) }}" class="font-mono text-xs font-bold text-navy-950 hover:underline">{{ $b->ref }}</a></td>
                    <td class="whitespace-nowrap"><span class="font-semibold text-navy-900">{{ $b->starts_at->format('M j, Y') }}</span><span class="block text-xs text-navy-400">{{ $b->starts_at->format('g:i A') }}</span></td>
                    <td><span class="font-semibold text-navy-900">{{ $b->boat }}</span>@if ($b->kind === 'owner') <x-admin.pill tone="brass">Owner</x-admin.pill>@endif<span class="block text-xs text-navy-400">{{ $b->experience }}</span></td>
                    <td>{{ $b->customer }}</td>
                    <td>{{ $b->guests }}</td>
                    <td>@if ($b->captain){{ $b->captain }}@elseif ($b->is_upcoming)<x-admin.pill tone="warning">Not assigned</x-admin.pill>@else<span class="text-navy-300">—</span>@endif</td>
                    <td><x-admin.pill :tone="$b->status_tone">{{ $b->status_label }}</x-admin.pill></td>
                    <td class="whitespace-nowrap font-bold text-navy-950">{{ money($b->total_cents, 'always') }}@if ($b->balance_cents > 0)<span class="block text-[11px] font-semibold text-navy-400">{{ money($b->balance_cents, 'never') }} balance due</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-12 text-center text-sm text-navy-500">No bookings match these filters.</td></tr>
            @endforelse
        </x-admin.table>
        @if ($bookings->hasPages())
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-navy-100 px-5 py-4 text-sm">
                <p class="text-navy-500">Showing {{ $bookings->firstItem() }}–{{ $bookings->lastItem() }} of {{ $bookings->total() }}</p>
                <nav class="flex gap-2" aria-label="Pages">
                    @if ($bookings->onFirstPage())<span class="rounded-full px-4 py-2 font-bold text-navy-300">Previous</span>@else<a href="{{ $bookings->previousPageUrl() }}" class="rounded-full px-4 py-2 font-bold text-navy-700 ring-1 ring-navy-200 hover:bg-navy-50">Previous</a>@endif
                    @if ($bookings->hasMorePages())<a href="{{ $bookings->nextPageUrl() }}" class="rounded-full px-4 py-2 font-bold text-navy-700 ring-1 ring-navy-200 hover:bg-navy-50">Next</a>@else<span class="rounded-full px-4 py-2 font-bold text-navy-300">Next</span>@endif
                </nav>
            </div>
        @endif
    </x-admin.card>
@endsection
