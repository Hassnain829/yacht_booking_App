@extends('layouts.admin', ['title' => 'Experiences', 'active' => 'experiences'])

@php
    $field = 'rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
    $tz = config('app.display_timezone');
    $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
@endphp

@section('content')
    <x-admin.page-header title="Experiences" description="The packages guests book: trip lengths, start times, days and the package fee. Hidden experiences can't be booked; in the Trash they are gone from the site.">
        <x-slot:actions>
            <x-button :href="route('admin.experiences.create')" variant="dark" icon-left="plus">Add an experience</x-button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mt-6 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <x-admin.list-tabs :url="route('admin.experiences.index')" :in-trash="$inTrash" :counts="$counts" />
        <form method="GET" action="{{ route('admin.experiences.index') }}" class="flex flex-wrap gap-2" role="search">
            @if ($inTrash)<input type="hidden" name="view" value="trash">@endif
            <label for="filter-category" class="sr-only">Category</label>
            <select id="filter-category" name="category" class="{{ $field }}">
                <option value="">All categories</option>
                @foreach ($categories as $key => $label)
                    <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <div class="relative min-w-0 flex-1 sm:w-64 sm:flex-none">
                <label for="filter-q" class="sr-only">Search experiences</label>
                <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                <input id="filter-q" name="q" type="search" maxlength="60" value="{{ $q }}" placeholder="Name or tagline" class="{{ $field }} w-full pl-10">
            </div>
            <x-button type="submit" variant="dark">Search</x-button>
        </form>
    </div>

    <x-admin.card flush class="mt-6">
        <x-admin.table min="min-w-[52rem]">
            <x-slot:head>
                <x-admin.sort column="name" label="Experience" :sort="$sort" :dir="$dir" />
                <th scope="col">Lengths · start times</th>
                <th scope="col">Days</th>
                <x-admin.sort column="boats" label="Boats" :sort="$sort" :dir="$dir" />
                <x-admin.sort column="fee" label="Package fee" :sort="$sort" :dir="$dir" />
                <th scope="col">Shown</th>
                <x-admin.sort column="sort" label="Order" :sort="$sort" :dir="$dir" />
                <th scope="col"><span class="sr-only">Open</span></th>
            </x-slot:head>
            @forelse ($experiences as $experience)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            @if ($experience->imageUrl())
                                <img src="{{ $experience->imageUrl() }}" alt="" loading="lazy" class="h-11 w-16 shrink-0 rounded-lg object-cover">
                            @else
                                <span class="grid h-11 w-16 shrink-0 place-items-center rounded-lg bg-brass-100 text-brass-700"><i data-lucide="{{ $experience->icon ?: 'sparkles' }}" class="h-5 w-5"></i></span>
                            @endif
                            <span class="min-w-0">
                                <a href="{{ route('admin.experiences.edit', $experience) }}" class="font-bold text-navy-950 hover:underline">{{ $experience->name }}</a>
                                <span class="block text-xs text-navy-400">{{ $categories[$experience->category] ?? ucfirst((string) $experience->category) }}</span>
                            </span>
                        </div>
                    </td>
                    <td class="text-xs">{{ implode(', ', $experience->durations ?? []) }} hrs<span class="block text-navy-400">{{ trans_choice('{0} No start times|{1} 1 start time|[2,*] :count start times', count($experience->time_slots ?? [])) }}</span></td>
                    <td class="text-xs">{{ $experience->allowed_weekdays ? implode(', ', array_map(fn ($d) => $days[$d], $experience->allowed_weekdays)) : 'Every day' }}</td>
                    <td>{{ $experience->boats_count }}</td>
                    <td class="font-semibold text-navy-950">{{ $experience->package_fee_cents ? money($experience->package_fee_cents) : '—' }}</td>
                    <td>
                        @if ($inTrash)
                            <x-admin.pill tone="danger">In the Trash</x-admin.pill>
                        @else
                            <x-admin.pill :tone="$experience->is_active ? 'success' : 'muted'">{{ $experience->is_active ? 'Shown' : 'Hidden' }}</x-admin.pill>
                            @if ($experience->is_featured)<x-admin.pill tone="brass">Featured</x-admin.pill>@endif
                        @endif
                    </td>
                    <td>{{ $experience->sort }}</td>
                    <td class="text-right"><a href="{{ route('admin.experiences.edit', $experience) }}" class="inline-flex items-center gap-1 text-sm font-bold text-navy-600 hover:text-navy-950">{{ $inTrash ? 'Open' : 'Edit' }}<i data-lucide="chevron-right" class="h-4 w-4"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-12 text-center text-sm text-navy-500">
                    @if ($q !== '' || $category)
                        No experiences match these filters.
                    @elseif ($inTrash)
                        The Trash is empty.
                    @else
                        No experiences yet. <a href="{{ route('admin.experiences.create') }}" class="font-bold text-navy-950 underline">Add the first one</a>.
                    @endif
                </td></tr>
            @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$experiences" />
    </x-admin.card>
@endsection
