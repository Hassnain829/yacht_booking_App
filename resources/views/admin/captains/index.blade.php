@extends('layouts.admin', ['title' => 'Captains', 'active' => 'captains'])

@php
    $field = 'rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
@endphp

@section('content')
    <x-admin.page-header title="Captains" description="Licensed captains for your trips. Assignments never include guests' contact details. Captain accounts, trip offers and pay come in Phase 8.">
        <x-slot:actions>
            <x-button :href="route('admin.captains.create')" variant="dark" icon-left="user-plus">Add a captain</x-button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mt-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <x-admin.list-tabs :url="route('admin.captains.index')" :in-trash="$inTrash" :counts="$counts" />
        <form method="GET" action="{{ route('admin.captains.index') }}" class="flex flex-wrap gap-2" role="search">
            @if ($inTrash)<input type="hidden" name="view" value="trash">@endif
            <label for="filter-status" class="sr-only">Status</label>
            <select id="filter-status" name="status" class="{{ $field }}">
                <option value="">Active or not</option>
                <option value="active" @selected($filters['status'] === 'active')>Active</option>
                <option value="inactive" @selected($filters['status'] === 'inactive')>Inactive</option>
            </select>
            <label for="filter-license" class="sr-only">License</label>
            <select id="filter-license" name="license" class="{{ $field }}">
                <option value="">Any license date</option>
                <option value="expiring" @selected($filters['license'] === 'expiring')>Expires within 30 days</option>
                <option value="expired" @selected($filters['license'] === 'expired')>Expired</option>
            </select>
            <div class="relative min-w-0 flex-1 sm:w-56 sm:flex-none">
                <label for="filter-q" class="sr-only">Search captains</label>
                <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                <input id="filter-q" name="q" type="search" maxlength="60" value="{{ $filters['q'] }}" placeholder="Name or email" class="{{ $field }} w-full pl-10">
            </div>
            <x-button type="submit" variant="dark">Filter</x-button>
        </form>
    </div>

    <x-admin.card flush class="mt-6">
        <x-admin.table min="min-w-[56rem]">
            <x-slot:head>
                <x-admin.sort column="name" label="Captain" :sort="$sort" :dir="$dir" />
                <th scope="col">Contact</th>
                <th scope="col">License</th>
                <x-admin.sort column="expires" label="Expires" :sort="$sort" :dir="$dir" />
                <x-admin.sort column="pay" label="Pay rate" :sort="$sort" :dir="$dir" />
                <th scope="col">Status</th>
                <th scope="col"><span class="sr-only">Open</span></th>
            </x-slot:head>
            @forelse ($captains as $captain)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            @if ($captain->photoUrl())
                                <img src="{{ $captain->photoUrl() }}" alt="" loading="lazy" class="h-9 w-9 shrink-0 rounded-full object-cover">
                            @else
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-navy-950 text-xs font-bold text-brass-300">{{ $captain->initials() }}</span>
                            @endif
                            <a href="{{ route('admin.captains.edit', $captain) }}" class="font-bold text-navy-950 hover:underline">{{ $captain->name }}</a>
                        </div>
                    </td>
                    <td><span class="block">{{ $captain->email }}</span><span class="text-xs text-navy-400">{{ $captain->phone ?: '—' }}</span></td>
                    <td><span class="block">{{ $captain->license_type->label() }}</span><span class="font-mono text-xs text-navy-400">{{ $captain->maskedLicense() ?? '—' }} · max {{ $captain->max_passengers_licensed }}</span></td>
                    <td class="whitespace-nowrap">
                        {{ $captain->license_expires_on?->format('M j, Y') ?? '—' }}
                        @if ($captain->licenseExpired())<x-admin.pill tone="danger" class="ml-1">Expired</x-admin.pill>@elseif ($captain->licenseExpiresSoon())<x-admin.pill tone="warning" class="ml-1">Soon</x-admin.pill>@endif
                    </td>
                    <td class="whitespace-nowrap font-semibold text-navy-950">{{ money($captain->pay_rate_cents) }}/hr</td>
                    <td>
                        @if ($inTrash)
                            <x-admin.pill tone="danger">In the Trash</x-admin.pill>
                        @else
                            <x-admin.pill :tone="$captain->is_active ? 'success' : 'muted'">{{ $captain->is_active ? 'Active' : 'Inactive' }}</x-admin.pill>
                        @endif
                    </td>
                    <td class="text-right"><a href="{{ route('admin.captains.edit', $captain) }}" class="inline-flex items-center gap-1 text-sm font-bold text-navy-600 hover:text-navy-950">{{ $inTrash ? 'Open' : 'Edit' }}<i data-lucide="chevron-right" class="h-4 w-4"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-12 text-center text-sm text-navy-500">
                    @if (array_filter($filters))
                        No captains match these filters.
                    @elseif ($inTrash)
                        The Trash is empty.
                    @else
                        No captains yet. <a href="{{ route('admin.captains.create') }}" class="font-bold text-navy-950 underline">Add the first one</a>.
                    @endif
                </td></tr>
            @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$captains" />
    </x-admin.card>
@endsection
