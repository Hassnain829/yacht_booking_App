@extends('layouts.admin', ['title' => 'Promo codes', 'active' => 'promos'])

{{-- Admin → Promo codes (4.8): the codes guests type at checkout. The 3.4 building blocks (list tabs, sort, Trash). --}}
@php
    $field = 'rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
    $tz = config('app.display_timezone');
    $tones = ['Active' => 'success', 'Scheduled' => 'brass', 'Ended' => 'muted', 'Used up' => 'warning', 'Hidden' => 'muted'];
@endphp

@section('content')
    <x-admin.page-header title="Promo codes" description="Codes guests type at checkout. The platform pays for the discount; bookings keep the discount they were made with.">
        <x-slot:actions>
            <x-button :href="route('admin.promo-codes.create')" variant="dark" icon-left="plus">Add a promo code</x-button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="mt-6 flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <x-admin.list-tabs :url="route('admin.promo-codes.index')" :in-trash="$inTrash" :counts="$counts" />
        <form method="GET" action="{{ route('admin.promo-codes.index') }}" class="flex gap-2" role="search">
            @if ($inTrash)<input type="hidden" name="view" value="trash">@endif
            <div class="relative min-w-0 flex-1 md:w-72 md:flex-none">
                <label for="filter-q" class="sr-only">Search promo codes</label>
                <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
                <input id="filter-q" name="q" type="search" maxlength="60" value="{{ $q }}" placeholder="Code" class="{{ $field }} w-full pl-10 uppercase placeholder:normal-case">
            </div>
            <x-button type="submit" variant="dark">Search</x-button>
        </form>
    </div>

    <x-admin.card flush class="mt-6">
        <x-admin.table>
            <x-slot:head>
                <x-admin.sort column="code" label="Code" :sort="$sort" :dir="$dir" />
                <th scope="col">Discount</th>
                <x-admin.sort column="used" label="Used" :sort="$sort" :dir="$dir" />
                <x-admin.sort column="ends" label="Dates" :sort="$sort" :dir="$dir" />
                <th scope="col">Status</th>
                <th scope="col"><span class="sr-only">Open</span></th>
            </x-slot:head>
            @forelse ($promos as $promo)
                @php($state = $promo->state())
                <tr>
                    <td><a href="{{ route('admin.promo-codes.edit', $promo) }}" class="font-mono font-bold text-navy-950 hover:underline">{{ $promo->code }}</a></td>
                    <td>
                        <span class="font-semibold text-navy-900">{{ $promo->terms()->label() }}</span>
                        @if ($promo->min_subtotal_cents || $promo->max_discount_cents)
                            <span class="block text-xs text-navy-400">{{ collect([$promo->min_subtotal_cents ? 'bookings from '.money($promo->min_subtotal_cents) : null, $promo->max_discount_cents ? 'up to '.money($promo->max_discount_cents) : null])->filter()->join(' · ') }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap">{{ $promo->redemptions_count }}{{ $promo->max_redemptions ? ' / '.$promo->max_redemptions : '' }}@if ($promo->max_per_user)<span class="block text-xs text-navy-400">{{ $promo->max_per_user }} per guest</span>@endif</td>
                    <td class="whitespace-nowrap text-xs">{{ $promo->starts_at || $promo->ends_at ? ($promo->starts_at?->setTimezone($tz)->format('M j, Y') ?? 'Now').' – '.($promo->ends_at?->setTimezone($tz)->format('M j, Y') ?? 'no end') : 'Always' }}</td>
                    <td>
                        @if ($inTrash)
                            <x-admin.pill tone="danger">In the Trash</x-admin.pill>
                        @else
                            <x-admin.pill :tone="$tones[$state]">{{ $state }}</x-admin.pill>
                        @endif
                    </td>
                    <td class="text-right"><a href="{{ route('admin.promo-codes.edit', $promo) }}" class="inline-flex items-center gap-1 text-sm font-bold text-navy-600 hover:text-navy-950">{{ $inTrash ? 'Open' : 'Edit' }}<i data-lucide="chevron-right" class="h-4 w-4"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-12 text-center text-sm text-navy-500">
                    @if ($q !== '')
                        No promo codes match "{{ $q }}".
                    @elseif ($inTrash)
                        The Trash is empty.
                    @else
                        No promo codes yet. <a href="{{ route('admin.promo-codes.create') }}" class="font-bold text-navy-950 underline">Add the first one</a>.
                    @endif
                </td></tr>
            @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$promos" />
    </x-admin.card>
@endsection
