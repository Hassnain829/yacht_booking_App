@extends('layouts.admin', ['title' => 'Booking calendar', 'active' => 'bookings'])

{{--
    Admin → Bookings → Calendar (4.8): a month of trips by Chicago date — held (not paid yet), confirmed and past.
    A 7-day grid on large screens; on smaller ones only the days with trips, as a list.
--}}
@php
    $start = $first->startOfWeek(Carbon\CarbonInterface::SUNDAY);
    $days = [];
    for ($day = $start; $day->lessThan($first->addMonth()) || $day->dayOfWeek !== Carbon\CarbonInterface::SUNDAY; $day = $day->addDay()) {
        $days[] = $day;
    }
    $tz = config('app.display_timezone');
    $count = $trips->flatten()->count();
@endphp

@section('content')
    <x-admin.page-header title="Booking calendar" :description="trans_choice('{0} No trips in :month.|{1} 1 trip in :month.|[2,*] :count trips in :month.', $count, ['month' => $first->format('F Y')])" :back="['All bookings', route('admin.bookings.index')]">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <a href="{{ route('admin.bookings.calendar', ['month' => $previous]) }}" class="grid h-10 w-10 place-items-center rounded-full bg-white ring-1 ring-navy-200 hover:bg-navy-50" aria-label="Previous month"><i data-lucide="chevron-left" class="h-4 w-4"></i></a>
                <span class="min-w-32 text-center text-sm font-bold text-navy-950">{{ $first->format('F Y') }}</span>
                <a href="{{ route('admin.bookings.calendar', ['month' => $next]) }}" class="grid h-10 w-10 place-items-center rounded-full bg-white ring-1 ring-navy-200 hover:bg-navy-50" aria-label="Next month"><i data-lucide="chevron-right" class="h-4 w-4"></i></a>
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card flush class="mt-6">
        <div class="hidden grid-cols-7 border-b border-navy-100 bg-navy-50/60 text-center text-[11px] font-bold uppercase tracking-wider text-navy-400 lg:grid" aria-hidden="true">
            @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $weekday)
                <div class="py-2.5">{{ $weekday }}</div>
            @endforeach
        </div>
        <ol class="grid gap-px bg-navy-100 lg:grid-cols-7">
            @foreach ($days as $day)
                @php
                    $date = $day->toDateString();
                    $dayTrips = $trips->get($date, collect());
                    $inMonth = $day->month === $first->month;
                @endphp
                <li @class([
                    'bg-white p-3 lg:min-h-32',
                    'hidden lg:block' => $dayTrips->isEmpty() || ! $inMonth,
                    'lg:bg-navy-50/40' => ! $inMonth,
                ])>
                    <p @class(['text-xs font-bold', 'text-navy-300' => ! $inMonth, 'text-navy-700' => $inMonth])>
                        <span class="lg:hidden">{{ $day->format('D, M j') }}</span>
                        <span @class(['hidden lg:inline-grid lg:h-6 lg:w-6 lg:place-items-center lg:rounded-full', 'lg:bg-navy-950 lg:text-white' => $date === $today])>{{ $day->day }}</span>
                    </p>
                    @if ($inMonth)
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($dayTrips as $trip)
                                <li>
                                    <a href="{{ route('admin.bookings.show', $trip->reference) }}" @class([
                                        'block rounded-lg px-2 py-1.5 text-[11px] leading-tight ring-1 transition hover:ring-navy-950',
                                        'bg-amber-50 ring-amber-200' => $trip->status === App\Enums\BookingStatus::Hold,
                                        'bg-emerald-50 ring-emerald-200' => $trip->status === App\Enums\BookingStatus::Confirmed,
                                        'bg-navy-50 ring-navy-100' => ! in_array($trip->status, [App\Enums\BookingStatus::Hold, App\Enums\BookingStatus::Confirmed], true),
                                    ])>
                                        <span class="font-bold text-navy-950">{{ $trip->starts_at->setTimezone($tz)->format('g:i A') }}</span> · {{ $trip->boat?->title }}
                                        <span class="block text-navy-500">{{ $trip->reference }} · {{ $trip->guests }} guests{{ $trip->status === App\Enums\BookingStatus::Hold ? ' · on hold' : '' }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </li>
            @endforeach
        </ol>
        @if ($count === 0)
            <p class="px-5 py-10 text-center text-sm text-navy-500 lg:hidden">No trips this month.</p>
        @endif
    </x-admin.card>
@endsection
