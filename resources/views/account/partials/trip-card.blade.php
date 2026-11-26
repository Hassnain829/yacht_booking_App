{{-- One trip in "My trips" (4.7): a booking of the signed-in guest. $trip (Booking), $cover, $complaintUntil. --}}
@php
    $tz = config('app.display_timezone');
    $start = $trip->starts_at->setTimezone($tz);
    $end = $trip->ends_at->setTimezone($tz);
    $status = $trip->status;
    $paid = $trip->payments->last();
    $card = $paid?->card_last4 ? ucfirst((string) $paid->card_brand).' •••• '.$paid->card_last4 : 'your card';
    $isHold = $status === App\Enums\BookingStatus::Hold;
    $isUpcoming = in_array($status, [App\Enums\BookingStatus::Hold, App\Enums\BookingStatus::Confirmed], true) && $trip->ends_at->isFuture();
    $canReport = $status === App\Enums\BookingStatus::Completed && $complaintUntil->isFuture();
    [$label, $class] = match ($status) {
        App\Enums\BookingStatus::Hold => ['Awaiting payment', 'bg-amber-100 text-amber-800'],
        App\Enums\BookingStatus::Confirmed => $isUpcoming ? ['Confirmed', 'bg-emerald-100 text-emerald-800'] : ['Trip ended', 'bg-navy-100 text-navy-700'],
        App\Enums\BookingStatus::Completed => ['Completed', 'bg-navy-100 text-navy-700'],
        App\Enums\BookingStatus::Cancelled => ['Cancelled', 'bg-red-100 text-red-700'],
        default => [$status->label(), 'bg-navy-100 text-navy-700'],
    };
    $payment = match (true) {
        $isHold => 'Not paid yet · your time is held until '.$trip->hold_expires_at->setTimezone($tz)->format('g:i A'),
        $status === App\Enums\BookingStatus::Cancelled => $trip->amount_refunded_cents > 0 ? 'Refunded '.money($trip->amount_refunded_cents, 'always').' to '.$card : 'Cancelled',
        default => 'Paid in full · '.money($trip->amount_paid_cents, 'always'),
    };
    $link = $isHold ? route('checkout.show', $trip) : route('account.trips.show', $trip);
@endphp
<article class="flex flex-col gap-5 rounded-[1.75rem] bg-white p-4 shadow-card ring-1 ring-navy-100 sm:flex-row sm:p-5">
    <div class="relative aspect-[16/10] w-full shrink-0 overflow-hidden rounded-2xl bg-navy-50 sm:aspect-auto sm:h-40 sm:w-60">
        <img src="{{ asset($cover) }}" alt="" loading="lazy" @class(['h-full w-full object-cover', 'grayscale' => $status === App\Enums\BookingStatus::Cancelled])>
        <span class="absolute bottom-2 left-2 rounded-md bg-navy-950/75 px-2 py-0.5 text-[10px] font-bold text-white backdrop-blur">{{ $trip->boat->title }}</span>
    </div>
    <div class="flex min-w-0 flex-1 flex-col">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wider {{ $class }}">{{ $label }}</span>
            <span class="text-xs text-navy-400">{{ $trip->reference }}</span>
        </div>
        <h2 class="mt-2 truncate font-display text-2xl text-navy-950">{{ $trip->experience?->name ?? 'Private charter' }}</h2>
        <p class="mt-1 text-sm text-navy-600">{{ $start->format('D, M j, Y') }} · {{ $start->format('g:i A') }} – {{ $end->format('g:i A') }} · {{ $trip->guests }} {{ Str::plural('guest', $trip->guests) }}</p>
        @if ($trip->boat->harbor)
            <p class="mt-1 inline-flex items-center gap-1.5 text-xs text-navy-500"><i data-lucide="map-pin" class="h-3.5 w-3.5 text-brass-500"></i>{{ $trip->boat->harbor->name }}</p>
        @endif
        <p class="mt-2 text-xs font-semibold text-navy-600">{{ $payment }}</p>
        <div class="mt-4 flex flex-wrap items-center justify-end gap-2 sm:mt-auto sm:pt-4">
            @if ($canReport)
                <a href="{{ route('account.trips.show', $trip) }}#report" class="rounded-full px-4 py-2.5 text-xs font-bold text-navy-600 hover:bg-navy-50 hover:text-navy-950">Report a problem</a>
            @endif
            <x-button :href="$link" :variant="$isUpcoming ? 'primary' : 'outline'" class="uppercase tracking-wider">{{ $isHold ? 'Finish checkout' : 'View trip' }}</x-button>
        </div>
    </div>
</article>
