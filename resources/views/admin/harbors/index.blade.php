@extends('layouts.admin', ['title' => 'Harbors', 'active' => 'harbors'])

@php
    $field = 'rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
    $tz = config('app.display_timezone');
@endphp

@section('content')
    <x-admin.page-header title="Harbors" description="Where guests board. Hidden harbors stay on their boats' pages but can't be chosen for new boats; a harbor in the Trash is hidden everywhere.">
        <x-slot:actions>
            <x-button :href="route('admin.harbors.create')" variant="dark" icon-left="plus">Add a harbor</x-button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mt-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <x-admin.list-tabs :url="route('admin.harbors.index')" :in-trash="$inTrash" :counts="$counts" />
        <form method="GET" action="{{ route('admin.harbors.index') }}" class="flex gap-2" role="search">
            @if ($inTrash)<input type="hidden" name="view" value="trash">@endif
            <div class="relative min-w-0 flex-1 md:w-72 md:flex-none">
                <label for="filter-q" class="sr-only">Search harbors</label>
                <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                <input id="filter-q" name="q" type="search" maxlength="60" value="{{ $q }}" placeholder="Name or area" class="{{ $field }} w-full pl-10">
            </div>
            <x-button type="submit" variant="dark">Search</x-button>
        </form>
    </div>

    <x-admin.card flush class="mt-6">
        <x-admin.table>
            <x-slot:head>
                <x-admin.sort column="name" label="Harbor" :sort="$sort" :dir="$dir" />
                <x-admin.sort column="boats" label="Boats" :sort="$sort" :dir="$dir" />
                <th scope="col">Shown</th>
                <x-admin.sort column="sort" label="Order" :sort="$sort" :dir="$dir" />
                <x-admin.sort column="updated" label="{{ $inTrash ? 'Last changed' : 'Updated' }}" :sort="$sort" :dir="$dir" />
                <th scope="col"><span class="sr-only">Open</span></th>
            </x-slot:head>
            @forelse ($harbors as $harbor)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            @if ($harbor->imageUrl())
                                <img src="{{ $harbor->imageUrl() }}" alt="" loading="lazy" class="h-11 w-16 shrink-0 rounded-lg object-cover">
                            @else
                                <span class="grid h-11 w-16 shrink-0 place-items-center rounded-lg bg-navy-50 text-navy-300"><i data-lucide="anchor" class="h-5 w-5"></i></span>
                            @endif
                            <span class="min-w-0">
                                <a href="{{ route('admin.harbors.edit', $harbor) }}" class="font-bold text-navy-950 hover:underline">{{ $harbor->name }}</a>
                                <span class="block text-xs text-navy-400">{{ $harbor->area ?: '—' }}</span>
                            </span>
                        </div>
                    </td>
                    <td>{{ $harbor->boats_count }}</td>
                    <td>
                        @if ($inTrash)
                            <x-admin.pill tone="danger">In the Trash</x-admin.pill>
                        @else
                            <x-admin.pill :tone="$harbor->is_active ? 'success' : 'muted'">{{ $harbor->is_active ? 'Shown' : 'Hidden' }}</x-admin.pill>
                        @endif
                    </td>
                    <td>{{ $harbor->sort }}</td>
                    <td class="whitespace-nowrap text-navy-500">{{ ($inTrash ? $harbor->deleted_at : $harbor->updated_at)?->timezone($tz)->format('M j, Y') }}</td>
                    <td class="text-right"><a href="{{ route('admin.harbors.edit', $harbor) }}" class="inline-flex items-center gap-1 text-sm font-bold text-navy-600 hover:text-navy-950">{{ $inTrash ? 'Open' : 'Edit' }}<i data-lucide="chevron-right" class="h-4 w-4"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-12 text-center text-sm text-navy-500">
                    @if ($q !== '')
                        No harbors match "{{ $q }}".
                    @elseif ($inTrash)
                        The Trash is empty.
                    @else
                        No harbors yet. <a href="{{ route('admin.harbors.create') }}" class="font-bold text-navy-950 underline">Add the first one</a>.
                    @endif
                </td></tr>
            @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$harbors" />
    </x-admin.card>
@endsection
