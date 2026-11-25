@extends('layouts.app', [
    'title' => 'Bookings',
    'page' => 'owner',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@section('content')
    <x-owner.shell active="bookings" heading="Bookings" intro="Every trip on your boats. Our team assigns a licensed captain to each trip and sends you the details.">
        <dl class="mt-10 grid grid-cols-3 divide-x divide-navy-100 rounded-3xl bg-white py-5 text-center shadow-xs ring-1 ring-navy-100">
            <div class="flex flex-col-reverse px-3"><dt class="mt-1 text-xs text-navy-500">Upcoming trips</dt><dd class="text-xl font-extrabold text-navy-950">{{ $groups['upcoming']->count() }}</dd></div>
            <div class="flex flex-col-reverse px-3"><dt class="mt-1 text-xs text-navy-500">Completed trips</dt><dd class="text-xl font-extrabold text-navy-950">{{ $groups['past']->count() }}</dd></div>
            <div class="flex flex-col-reverse px-3"><dt class="mt-1 text-xs text-navy-500">Pending earnings</dt><dd class="text-xl font-extrabold text-navy-950">{{ money($balances['pending'], 'never') }}</dd></div>
        </dl>

        <nav class="mt-10 inline-flex gap-1 rounded-full bg-white p-1 text-xs font-bold shadow-xs ring-1 ring-navy-100" aria-label="Booking filter">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('owner.bookings.index', $key === 'upcoming' ? [] : ['tab' => $key]) }}" @class([
                    'rounded-full px-4 py-2',
                    'bg-navy-950 text-white' => $tab === $key,
                    'text-navy-500 hover:text-navy-950' => $tab !== $key,
                ]) @if ($tab === $key) aria-current="page" @endif>{{ $label }} <span class="{{ $tab === $key ? 'text-white/60' : 'text-navy-300' }}">{{ $groups[$key]->count() }}</span></a>
            @endforeach
        </nav>

        <div class="mt-6 space-y-4">
            @forelse ($list as $booking)
                <article class="flex flex-col gap-5 rounded-[1.75rem] bg-white p-4 shadow-card ring-1 ring-navy-100 sm:flex-row sm:items-center sm:p-5" id="booking-{{ $booking->ref }}">
                    <div class="flex items-center gap-4 sm:w-auto">
                        <div class="grid h-20 w-20 shrink-0 place-items-center rounded-2xl {{ $booking->status === 'cancelled' ? 'bg-navy-50 text-navy-400' : 'bg-navy-950 text-white' }} text-center leading-none">
                            <span>
                                <span class="block text-[10px] font-bold uppercase tracking-[0.2em] {{ $booking->status === 'cancelled' ? '' : 'text-brass-300' }}">{{ $booking->starts_at->format('M') }}</span>
                                <span class="mt-1 block font-display text-3xl">{{ $booking->starts_at->format('j') }}</span>
                                <span class="mt-1 block text-[10px] font-semibold opacity-70">{{ $booking->starts_at->format('D') }}</span>
                            </span>
                        </div>
                        <div class="min-w-0 sm:hidden">
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wider {{ $booking->status_class }}">{{ $booking->status_label }}</span>
                            <p class="mt-2 font-display text-xl text-navy-950">{{ $booking->experience }}</p>
                        </div>
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="hidden flex-wrap items-center gap-2 sm:flex">
                            <span class="rounded-full px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wider {{ $booking->status_class }}">{{ $booking->status_label }}</span>
                            <span class="text-xs text-navy-400">{{ $booking->ref }}</span>
                        </div>
                        <h2 class="mt-2 hidden truncate font-display text-2xl text-navy-950 sm:block">{{ $booking->experience }}</h2>
                        <p class="mt-1 text-sm text-navy-600">{{ $booking->boat->short_title }} · {{ $booking->starts_at->format('g:i A') }} – {{ $booking->ends_at->format('g:i A') }} · {{ $booking->guests }} {{ Str::plural('guest', $booking->guests) }}</p>
                        <p class="mt-1 text-xs text-navy-400"><span class="sm:hidden">{{ $booking->ref }} · </span>Booked by {{ $booking->customer }}</p>
                        @if ($booking->status !== 'cancelled')
                            <p class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold {{ $booking->captain ? 'text-navy-600' : 'text-amber-700' }}">
                                <i data-lucide="ship-wheel" class="h-4 w-4 {{ $booking->captain ? 'text-brass-500' : 'text-amber-600' }}"></i>
                                {{ $booking->captain ? 'Captain: '.$booking->captain : 'Captain to be assigned by our team' }}
                            </p>
                        @else
                            <p class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-navy-500"><i data-lucide="undo-2" class="h-4 w-4 text-navy-400"></i>Cancelled by the guest · fully refunded</p>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-4 border-t border-navy-100 pt-4 sm:block sm:border-0 sm:pt-0 sm:text-right">
                        <p class="text-xs text-navy-500">Your earnings</p>
                        <div class="sm:mt-1">
                            <p class="text-xl font-extrabold text-navy-950">{{ money($booking->net_cents, 'never') }}</p>
                            <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-[11px] font-bold {{ $booking->earning_class }}">{{ $booking->earning_label }}</span>
                            @if ($booking->earning_status === 'pending' && ! $booking->is_upcoming)
                                <p class="mt-1 text-[11px] text-navy-400">Available {{ $booking->available_at->format('M j, g:i A') }}</p>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-[2rem] border-2 border-dashed border-navy-200 bg-white px-6 py-14 text-center">
                    <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-navy-50 text-navy-400"><i data-lucide="calendar-x" class="h-7 w-7"></i></span>
                    <h2 class="mt-4 font-display text-2xl text-navy-950">No {{ strtolower($tabs[$tab]) }} bookings</h2>
                    <p class="mx-auto mt-2 max-w-md text-sm text-navy-500">New bookings appear here as soon as a guest books and pays.</p>
                </div>
            @endforelse
        </div>
    </x-owner.shell>
@endsection
