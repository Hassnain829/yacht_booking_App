@extends('layouts.admin', ['title' => 'Add-ons', 'active' => 'addons'])

@php
    $field = 'rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
@endphp

@section('content')
    <x-admin.page-header title="Add-ons" description="Extras guests can add to a booking, per booking or per guest. Bookings keep the price they were made with.">
        <x-slot:actions>
            <x-button :href="route('admin.addons.create')" variant="dark" icon-left="plus">Add an add-on</x-button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mt-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <x-admin.list-tabs :url="route('admin.addons.index')" :in-trash="$inTrash" :counts="$counts" />
        <form method="GET" action="{{ route('admin.addons.index') }}" class="flex gap-2" role="search">
            @if ($inTrash)<input type="hidden" name="view" value="trash">@endif
            <div class="relative min-w-0 flex-1 md:w-72 md:flex-none">
                <label for="filter-q" class="sr-only">Search add-ons</label>
                <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                <input id="filter-q" name="q" type="search" maxlength="60" value="{{ $q }}" placeholder="Name or description" class="{{ $field }} w-full pl-10">
            </div>
            <x-button type="submit" variant="dark">Search</x-button>
        </form>
    </div>

    <x-admin.card flush class="mt-6">
        <x-admin.table>
            <x-slot:head>
                <x-admin.sort column="name" label="Add-on" :sort="$sort" :dir="$dir" />
                <x-admin.sort column="price" label="Price" :sort="$sort" :dir="$dir" />
                <th scope="col">Max per booking</th>
                <th scope="col">Shown</th>
                <x-admin.sort column="sort" label="Order" :sort="$sort" :dir="$dir" />
                <th scope="col"><span class="sr-only">Open</span></th>
            </x-slot:head>
            @forelse ($addons as $addon)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-brass-100 text-brass-700"><i data-lucide="{{ $addon->icon ?: 'gift' }}" class="h-4 w-4"></i></span>
                            <span class="min-w-0">
                                <a href="{{ route('admin.addons.edit', $addon) }}" class="font-bold text-navy-950 hover:underline">{{ $addon->name }}</a>
                                <span class="block text-xs text-navy-400">{{ $addon->description ?: '—' }}</span>
                            </span>
                        </div>
                    </td>
                    <td class="whitespace-nowrap font-semibold text-navy-950">{{ money($addon->price_cents) }}{{ $addon->unit === \App\Enums\AddonUnit::PerGuest ? ' / guest' : '' }}</td>
                    <td>{{ $addon->max_quantity }}</td>
                    <td>
                        @if ($inTrash)
                            <x-admin.pill tone="danger">In the Trash</x-admin.pill>
                        @else
                            <x-admin.pill :tone="$addon->is_active ? 'success' : 'muted'">{{ $addon->is_active ? 'Shown' : 'Hidden' }}</x-admin.pill>
                        @endif
                    </td>
                    <td>{{ $addon->sort }}</td>
                    <td class="text-right"><a href="{{ route('admin.addons.edit', $addon) }}" class="inline-flex items-center gap-1 text-sm font-bold text-navy-600 hover:text-navy-950">{{ $inTrash ? 'Open' : 'Edit' }}<i data-lucide="chevron-right" class="h-4 w-4"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-12 text-center text-sm text-navy-500">
                    @if ($q !== '')
                        No add-ons match "{{ $q }}".
                    @elseif ($inTrash)
                        The Trash is empty.
                    @else
                        No add-ons yet. <a href="{{ route('admin.addons.create') }}" class="font-bold text-navy-950 underline">Add the first one</a>.
                    @endif
                </td></tr>
            @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$addons" />
    </x-admin.card>
@endsection
