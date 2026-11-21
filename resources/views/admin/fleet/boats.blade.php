@extends('layouts.admin', ['title' => 'Boats', 'active' => 'boats', 'page' => 'admin-boats'])

@section('content')
    <x-admin.page-header title="Boats" description="House boats you run and approved owner boats. Turn a boat off to show it as Unavailable — it stays on the site but can't be booked.">
        <x-slot:actions>
            <x-button variant="dark" data-soon icon-left="plus">Add a house boat</x-button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card title="House boats" flush class="mt-6">
        <x-admin.table>
            <x-slot:head><th scope="col">Boat</th><th scope="col">Harbor</th><th scope="col">Guests</th><th scope="col">Hourly</th><th scope="col">Captain fee</th><th scope="col">Bookable</th></x-slot:head>
            @foreach ($house as $yacht)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <img src="{{ asset($yacht->cover) }}" alt="" loading="lazy" class="h-11 w-16 shrink-0 rounded-lg object-cover">
                            <span><a href="{{ route('yachts.show', $yacht->slug) }}" class="font-bold text-navy-950 hover:underline" target="_blank" rel="noopener">{{ $yacht->title }}</a><span class="block text-xs text-navy-400">{{ $yacht->category }} · {{ $yacht->length_ft }} ft</span></span>
                        </div>
                    </td>
                    <td>{{ $yacht->harbor->name }}</td>
                    <td>{{ $yacht->max_guests }}</td>
                    <td class="font-semibold text-navy-950">{{ money($yacht->hourly_rate_cents, 'never') }}</td>
                    <td>{{ money($yacht->captain_rate_cents, 'never') }}/hr</td>
                    <td>
                        <button type="button" role="switch" aria-checked="{{ $yacht->booking_enabled ? 'true' : 'false' }}" data-booking-toggle="{{ $yacht->title }}" class="group relative h-6 w-11 rounded-full bg-navy-200 transition aria-checked:bg-emerald-600" aria-label="{{ $yacht->title }} can be booked">
                            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all group-aria-checked:left-[22px]"></span>
                        </button>
                    </td>
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.card>

    <x-admin.card title="Owner boats" flush class="mt-6">
        <x-admin.table>
            <x-slot:head><th scope="col">Boat</th><th scope="col">Owner</th><th scope="col">Hourly</th><th scope="col">Policy</th><th scope="col">Bookable</th></x-slot:head>
            @forelse ($owned as $boat)
                <tr>
                    <td><span class="font-bold text-navy-950">{{ $boat->short_title }}</span><span class="block text-xs text-navy-400">{{ $boat->meta }}</span></td>
                    <td>{{ $boat->owner }}</td>
                    <td class="font-semibold text-navy-950">{{ money($boat->pricing['hourly_cents'], 'never') }}</td>
                    <td>{{ ucfirst($boat->cancellation) }}</td>
                    <td>
                        <button type="button" role="switch" aria-checked="{{ $boat->booking_enabled ? 'true' : 'false' }}" data-booking-toggle="{{ $boat->short_title }}" class="group relative h-6 w-11 rounded-full bg-navy-200 transition aria-checked:bg-emerald-600" aria-label="{{ $boat->short_title }} can be booked">
                            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all group-aria-checked:left-[22px]"></span>
                        </button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-10 text-center text-sm text-navy-500">No approved owner boats yet.</td></tr>
            @endforelse
        </x-admin.table>
    </x-admin.card>
@endsection
