{{-- One trip in "My trips". --}}
@php
    $payment = match (true) {
        $trip->status === 'cancelled' => 'Refunded '.money($trip->refunded_cents, 'always').' to '.$trip->card,
        $trip->balance_cents > 0 => 'Deposit paid · '.money($trip->balance_cents, 'always').' charged on '.$trip->balance_due->format('M j'),
        default => 'Paid in full · '.money($trip->paid_cents, 'always'),
    };
@endphp
<article class="flex flex-col gap-5 rounded-[1.75rem] bg-white p-4 shadow-card ring-1 ring-navy-100 sm:flex-row sm:p-5">
    <div class="relative aspect-[16/10] w-full shrink-0 overflow-hidden rounded-2xl bg-navy-50 sm:aspect-auto sm:h-40 sm:w-60">
        <img src="{{ asset($trip->yacht->cover) }}" alt="" loading="lazy" @class(['h-full w-full object-cover', 'grayscale' => $trip->status === 'cancelled'])>
        <span class="absolute bottom-2 left-2 rounded-md bg-navy-950/75 px-2 py-0.5 text-[10px] font-bold text-white backdrop-blur">{{ $trip->yacht->title }}</span>
    </div>
    <div class="flex min-w-0 flex-1 flex-col">
        <div class="flex flex-wrap items-center gap-2">
            <span class="rounded-full px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wider {{ $trip->status_class }}">{{ $trip->status_label }}</span>
            <span class="text-xs text-navy-400">{{ $trip->ref }}</span>
        </div>
        <h2 class="mt-2 truncate font-display text-2xl text-navy-950">{{ $trip->experience->name }}</h2>
        <p class="mt-1 text-sm text-navy-600">{{ $trip->starts_at->format('D, M j, Y') }} · {{ $trip->starts_at->format('g:i A') }} – {{ $trip->ends_at->format('g:i A') }} · {{ $trip->guests }} guests</p>
        <p class="mt-1 inline-flex items-center gap-1.5 text-xs text-navy-500"><i data-lucide="map-pin" class="h-3.5 w-3.5 text-brass-500"></i>{{ $trip->yacht->harbor->name }}</p>
        <p class="mt-2 text-xs font-semibold text-navy-600">{{ $payment }}</p>
        <div class="mt-4 flex flex-wrap items-center justify-end gap-2 sm:mt-auto sm:pt-4">
            @if ($trip->can_complain)
                <a href="{{ route('account.trips.show', $trip->ulid) }}#report" class="rounded-full px-4 py-2.5 text-xs font-bold text-navy-600 hover:bg-navy-50 hover:text-navy-950">Report a problem</a>
            @endif
            <x-button :href="route('account.trips.show', $trip->ulid)" :variant="$trip->is_upcoming ? 'primary' : 'outline'" class="uppercase tracking-wider">View trip</x-button>
        </div>
    </div>
</article>
