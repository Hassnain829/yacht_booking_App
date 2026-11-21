@extends('layouts.app', [
    'title' => 'Calendar',
    'page' => 'owner-calendar',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@section('content')
    <x-owner.shell active="calendar" heading="Calendar" intro="Trips and blocked dates for each approved boat. Block a day when your boat is out of service or you are using it yourself — guests can't book blocked days.">
        @if (! $boat)
            <div class="mt-10 rounded-[2rem] border-2 border-dashed border-navy-200 bg-white px-6 py-14 text-center">
                <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-navy-50 text-navy-400"><i data-lucide="calendar-days" class="h-7 w-7"></i></span>
                <h2 class="mt-4 font-display text-2xl text-navy-950">Your calendar opens after approval</h2>
                <p class="mx-auto mt-2 max-w-md text-sm text-navy-500">Once our team approves one of your boats, its trips and availability show up here.</p>
                <x-button :href="route('owner.boats.index')" variant="dark" class="mt-6">View my boats</x-button>
            </div>
        @else
            <div class="mt-10 flex flex-wrap items-center gap-2" aria-label="Boat">
                @foreach ($boats as $option)
                    <a href="{{ route('owner.calendar', ['boat' => $option->ulid]) }}" @class([
                        'inline-flex items-center gap-2 rounded-full px-4 py-2 text-xs font-bold ring-1 transition',
                        'bg-navy-950 text-white ring-navy-950' => $option->ulid === $boat->ulid,
                        'bg-white text-navy-700 ring-navy-100 hover:ring-navy-300' => $option->ulid !== $boat->ulid,
                    ]) @if ($option->ulid === $boat->ulid) aria-current="true" @endif><i data-lucide="sailboat" class="h-4 w-4"></i>{{ $option->short_title }}</a>
                @endforeach
            </div>

            <div class="mt-6 grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]" id="owner-calendar" data-calendar="{{ json_encode($calendar) }}">
                <section class="rounded-3xl bg-white p-4 shadow-xs ring-1 ring-navy-100 sm:p-6" aria-label="Month">
                    <div data-cal-month>
                        <noscript>
                            <p class="text-sm text-navy-500">Turn on JavaScript to see the month view. Your upcoming trips:</p>
                            <ul class="mt-3 space-y-2 text-sm">
                                @foreach ($calendar['bookings'] as $trip)
                                    @if ($trip['date'] >= $calendar['today'])
                                        <li>{{ $trip['date'] }} · {{ $trip['start'] }}–{{ $trip['end'] }} · {{ $trip['experience'] }}</li>
                                    @endif
                                @endforeach
                            </ul>
                        </noscript>
                    </div>
                </section>
                <aside class="rounded-3xl bg-white p-5 shadow-xs ring-1 ring-navy-100 sm:p-6 lg:sticky lg:top-28" data-cal-day aria-live="polite"></aside>
            </div>
        @endif
    </x-owner.shell>
@endsection
