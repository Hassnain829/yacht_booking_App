@extends('layouts.app', [
    'title' => 'My Trips',
    'page' => 'account',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@section('content')
    <x-account.shell active="trips" heading="My trips">
        <x-slot:actions>
            <x-button :href="route('yachts.index')" icon="arrow-right" class="uppercase tracking-wider">Book a new trip</x-button>
        </x-slot:actions>

        <section class="mt-10" aria-labelledby="upcoming-title">
            <h2 id="upcoming-title" class="text-xs font-bold uppercase tracking-[0.18em] text-navy-400">Upcoming</h2>
            <div class="mt-4 space-y-5">
                @forelse ($upcoming as $trip)
                    @include('account.partials.trip-card', ['trip' => $trip])
                @empty
                    <div class="rounded-[2rem] border-2 border-dashed border-navy-200 bg-white px-6 py-14 text-center">
                        <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-navy-50 text-navy-400"><i data-lucide="sailboat" class="h-7 w-7"></i></span>
                        <h3 class="mt-4 font-display text-2xl text-navy-950">No upcoming trips</h3>
                        <p class="mx-auto mt-2 max-w-md text-sm text-navy-500">Sunset cruises, swim parties and fireworks nights — your next day on the lake is a few clicks away.</p>
                        <x-button :href="route('yachts.index')" size="lg" class="mt-6">Plan a trip</x-button>
                    </div>
                @endforelse
            </div>
        </section>

        @if ($past->isNotEmpty())
            <section class="mt-12" aria-labelledby="past-title">
                <h2 id="past-title" class="text-xs font-bold uppercase tracking-[0.18em] text-navy-400">Past &amp; cancelled</h2>
                <div class="mt-4 space-y-5">
                    @foreach ($past as $trip)
                        @include('account.partials.trip-card', ['trip' => $trip])
                    @endforeach
                </div>
            </section>
        @endif
    </x-account.shell>
@endsection
