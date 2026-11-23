@extends('layouts.admin', ['title' => 'Boats', 'active' => 'boats', 'page' => 'admin-boats'])

@php
    $field = 'rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
    $tones = ['published' => 'success', 'paused' => 'muted', 'suspended' => 'danger', 'archived' => 'muted', 'draft' => 'navy', 'pending_review' => 'warning', 'changes_requested' => 'warning', 'approved' => 'brass', 'rejected' => 'danger'];
@endphp

@section('content')
    <x-admin.page-header title="Boats" description="Every boat on the site: your own (house) boats and owners' boats. Switch “Bookable” off to show a boat as Unavailable — it stays on the site but can't be booked.">
        <x-slot:actions>
            <x-button :href="route('admin.boats.create')" variant="dark" icon-left="plus">Add a house boat</x-button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mt-6 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <x-admin.list-tabs :url="route('admin.boats.index')" :in-trash="$inTrash" :counts="$counts" />
        <form method="GET" action="{{ route('admin.boats.index') }}" class="flex flex-wrap gap-2" role="search">
            @if ($inTrash)<input type="hidden" name="view" value="trash">@endif
            <label for="filter-ownership" class="sr-only">Owner</label>
            <select id="filter-ownership" name="ownership" class="{{ $field }}">
                <option value="">House + owner boats</option>
                <option value="house" @selected($filters['ownership'] === 'house')>House boats</option>
                <option value="owner" @selected($filters['ownership'] === 'owner')>Owner boats</option>
            </select>
            <label for="filter-status" class="sr-only">Status</label>
            <select id="filter-status" name="status" class="{{ $field }}">
                <option value="">Any status</option>
                <option value="published" @selected($filters['status'] === 'published')>Live</option>
                <option value="hidden" @selected($filters['status'] === 'hidden')>Hidden</option>
                <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                <option value="review" @selected($filters['status'] === 'review')>In listing review</option>
            </select>
            <label for="filter-bookable" class="sr-only">Bookable</label>
            <select id="filter-bookable" name="bookable" class="{{ $field }}">
                <option value="">Bookable or not</option>
                <option value="yes" @selected($filters['bookable'] === 'yes')>Bookable</option>
                <option value="no" @selected($filters['bookable'] === 'no')>Unavailable</option>
            </select>
            <div class="relative min-w-0 flex-1 sm:w-56 sm:flex-none">
                <label for="filter-q" class="sr-only">Search boats</label>
                <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                <input id="filter-q" name="q" type="search" maxlength="60" value="{{ $filters['q'] }}" placeholder="Name or type" class="{{ $field }} w-full pl-10">
            </div>
            <x-button type="submit" variant="dark">Filter</x-button>
            @if (array_filter($filters))
                <a href="{{ route('admin.boats.index', $inTrash ? ['view' => 'trash'] : []) }}" class="px-2 py-2.5 text-sm font-bold text-navy-500 hover:text-navy-950">Clear</a>
            @endif
        </form>
    </div>

    <x-admin.card flush class="mt-6">
        <x-admin.table min="min-w-[58rem]">
            <x-slot:head>
                <x-admin.sort column="title" label="Boat" :sort="$sort" :dir="$dir" />
                <th scope="col">Owner</th>
                <th scope="col">Harbor</th>
                <x-admin.sort column="guests" label="Guests" :sort="$sort" :dir="$dir" />
                <x-admin.sort column="hourly" label="Hourly" :sort="$sort" :dir="$dir" />
                <th scope="col">Status</th>
                <th scope="col">Bookable</th>
                <th scope="col"><span class="sr-only">Open</span></th>
            </x-slot:head>
            @forelse ($boats as $boat)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            @if ($boat->cover())
                                <img src="{{ $boat->cover()->url('thumb') }}" alt="" loading="lazy" class="h-11 w-16 shrink-0 rounded-lg object-cover">
                            @else
                                <span class="grid h-11 w-16 shrink-0 place-items-center rounded-lg bg-navy-50 text-navy-300"><i data-lucide="image-off" class="h-5 w-5"></i></span>
                            @endif
                            <span class="min-w-0">
                                <a href="{{ route('admin.boats.edit', $boat) }}" class="font-bold text-navy-950 hover:underline">{{ $boat->title }}</a>
                                <span class="block text-xs text-navy-400">{{ $boat->category ?: '—' }}{{ $boat->length_ft ? ' · '.$boat->length_ft.' ft' : '' }} · {{ trans_choice('{0} no photos|{1} 1 photo|[2,*] :count photos', $boat->photos->count()) }}</span>
                            </span>
                        </div>
                    </td>
                    <td>@if ($boat->isHouse())<x-admin.pill tone="navy">House</x-admin.pill>@else<span class="text-sm">{{ $boat->owner?->name ?? '—' }}</span>@endif</td>
                    <td>{{ $boat->harbor?->name ?? '—' }}</td>
                    <td>{{ $boat->max_guests }}</td>
                    <td class="whitespace-nowrap font-semibold text-navy-950">{{ $boat->hourly_rate_cents ? money($boat->hourly_rate_cents) : '—' }}</td>
                    <td>
                        @if ($inTrash)
                            <x-admin.pill tone="danger">In the Trash</x-admin.pill>
                        @else
                            <x-admin.pill :tone="$tones[$boat->status->value] ?? 'navy'">{{ $boat->status === \App\Enums\BoatStatus::Published ? 'Live' : $boat->status->label() }}</x-admin.pill>
                        @endif
                    </td>
                    <td>
                        @unless ($inTrash)
                            <button type="button" role="switch" aria-checked="{{ $boat->booking_enabled ? 'true' : 'false' }}" data-bookable-toggle data-url="{{ route('admin.boats.bookable', $boat) }}" data-title="{{ $boat->title }}" class="group relative h-6 w-11 rounded-full bg-navy-200 transition aria-checked:bg-emerald-600" aria-label="{{ $boat->title }} can be booked">
                                <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all group-aria-checked:left-[22px]"></span>
                            </button>
                        @endunless
                    </td>
                    <td class="text-right"><a href="{{ route('admin.boats.edit', $boat) }}" class="inline-flex items-center gap-1 text-sm font-bold text-navy-600 hover:text-navy-950">{{ $inTrash ? 'Open' : 'Edit' }}<i data-lucide="chevron-right" class="h-4 w-4"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="8" class="py-12 text-center text-sm text-navy-500">
                    @if (array_filter($filters))
                        No boats match these filters.
                    @elseif ($inTrash)
                        The Trash is empty.
                    @else
                        No boats yet. <a href="{{ route('admin.boats.create') }}" class="font-bold text-navy-950 underline">Add the first one</a>.
                    @endif
                </td></tr>
            @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$boats" />
    </x-admin.card>
@endsection
