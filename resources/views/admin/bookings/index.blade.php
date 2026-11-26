@extends('layouts.admin', ['title' => 'Bookings', 'active' => 'bookings'])

{{-- Admin → Bookings (4.8): every trip, searchable, with whitelisted filters and sort. Expired holds only on request. --}}
@php
    $select = 'rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
    $tz = config('app.display_timezone');
@endphp

@section('content')
    <x-admin.page-header title="Bookings" description="Every trip on house and owner boats. Search by reference, guest or boat.">
        <x-slot:actions>
            <x-button :href="route('admin.bookings.calendar')" variant="outline" icon-left="calendar-days">Calendar</x-button>
        </x-slot:actions>
    </x-admin.page-header>

    <form method="GET" action="{{ route('admin.bookings.index') }}" class="mt-6 flex flex-wrap items-end gap-3 rounded-2xl bg-white p-4 shadow-xs ring-1 ring-navy-100" role="search">
        <div class="relative min-w-[14rem] flex-1">
            <label for="filter-q" class="sr-only">Search</label>
            <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
            <input id="filter-q" name="q" type="search" maxlength="60" value="{{ $filters['q'] }}" placeholder="CYB-…, guest or boat" class="{{ $select }} w-full pl-10">
        </div>
        <label class="sr-only" for="filter-status">Status</label>
        <select id="filter-status" name="status" class="{{ $select }}">
            <option value="">All but expired holds</option>
            @foreach ($statuses as $value => $label)
                <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <label class="sr-only" for="filter-when">When</label>
        <select id="filter-when" name="when" class="{{ $select }}">
            <option value="">Any date</option>
            <option value="upcoming" @selected($filters['when'] === 'upcoming')>Upcoming</option>
            <option value="past" @selected($filters['when'] === 'past')>Past</option>
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
            <option value="none" @selected($filters['captain'] === 'none')>No captain yet</option>
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
                @php($start = $b->starts_at->setTimezone($tz))
                <tr>
                    <td><a href="{{ route('admin.bookings.show', $b->reference) }}" class="font-mono text-xs font-bold text-navy-950 hover:underline">{{ $b->reference }}</a></td>
                    <td class="whitespace-nowrap"><span class="font-semibold text-navy-900">{{ $start->format('M j, Y') }}</span><span class="block text-xs text-navy-400">{{ $start->format('g:i A') }} · {{ $b->hours }} h</span></td>
                    <td><span class="font-semibold text-navy-900">{{ $b->boat?->title }}</span>@if ($b->boat?->ownership === App\Enums\BoatOwnership::Owner) <x-admin.pill tone="brass">Owner</x-admin.pill>@endif<span class="block text-xs text-navy-400">{{ $b->experience?->name ?? 'Private charter' }}</span></td>
                    <td>{{ trim($b->contact_first_name.' '.$b->contact_last_name) ?: '—' }}</td>
                    <td>{{ $b->guests }}</td>
                    <td>@if ($b->captain){{ $b->captain->name }}@elseif ($b->status === App\Enums\BookingStatus::Confirmed && $b->ends_at->isFuture())<x-admin.pill tone="warning">Not assigned</x-admin.pill>@else<span class="text-navy-300">—</span>@endif</td>
                    <td><x-admin.pill :tone="$b->status->tone()">{{ $b->status->label() }}</x-admin.pill></td>
                    <td class="whitespace-nowrap font-bold text-navy-950">{{ money($b->total_cents, 'always') }}@if ($b->status === App\Enums\BookingStatus::Hold)<span class="block text-[11px] font-semibold text-navy-400">not paid yet</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-12 text-center text-sm text-navy-500">{{ array_filter($filters) ? 'No bookings match these filters.' : 'No bookings yet.' }}</td></tr>
            @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$bookings" />
    </x-admin.card>
@endsection
