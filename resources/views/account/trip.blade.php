@extends('layouts.app', [
    'title' => 'Trip '.$trip->reference,
    'page' => 'account-trip',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

{{--
    Trip detail (PLAN 4.7): the guest's own booking, in the Phase 1 design. The refund preview comes from the policy
    the trip was booked under, less the card processing fee (CancellationPolicyService, D23). Since 5.4 the guest
    cancels here (the dialog posts to account.trips.cancel); the refund waits for the team's approval and its state
    shows on this page. The problem report (Phase 7) isn't built yet: that box says how to reach the team.
--}}
@php
    $tz = config('app.display_timezone');
    $boat = $trip->boat;
    $harbor = $boat->harbor;
    $start = $trip->starts_at->setTimezone($tz);
    $end = $trip->ends_at->setTimezone($tz);
    $status = $trip->status;
    $card = $payment?->card_last4 ? ucfirst((string) $payment->card_brand).' •••• '.$payment->card_last4 : 'your card';
    $mapUrl = $harbor ? 'https://www.google.com/maps/search/?api=1&query='.rawurlencode((string) $harbor->map_query) : null;
    $policy = $trip->policy_snapshot ?? [];
    [$label, $class] = match ($status) {
        App\Enums\BookingStatus::Confirmed => $upcoming ? ['Confirmed', 'bg-emerald-100 text-emerald-800'] : ['Trip ended', 'bg-navy-100 text-navy-700'],
        App\Enums\BookingStatus::Completed => ['Completed', 'bg-navy-100 text-navy-700'],
        App\Enums\BookingStatus::Cancelled => ['Cancelled', 'bg-red-100 text-red-700'],
        default => [$status->label(), 'bg-navy-100 text-navy-700'],
    };
    $refundLine = fn (App\Models\Refund $refund) => match ($refund->status) {
        App\Enums\RefundStatus::Requested => 'Your refund of '.money($refund->amount_cents, 'always').' is being reviewed by our team — usually within 1 business day. We\'ll email you when it\'s sent.',
        App\Enums\RefundStatus::Pending => 'Your refund of '.money($refund->amount_cents, 'always').' is on its way to '.$card.'. It can take 5–10 business days to show.',
        App\Enums\RefundStatus::Succeeded => money($refund->amount_cents, 'always').' was refunded to '.$card.($refund->refunded_at ? ' on '.$refund->refunded_at->setTimezone($tz)->format('M j, Y') : '').'.',
        App\Enums\RefundStatus::Rejected => 'Your refund request was declined'.($refund->decision_note ? ': '.$refund->decision_note : '.'),
        default => 'We couldn\'t send your refund of '.money($refund->amount_cents, 'always').' yet. Our team is on it.',
    };
    $flash = match (session('status')) {
        'cancelled-refund-requested' => 'Your trip is cancelled. Your refund is being reviewed by our team, and we\'ll email you when it\'s sent.',
        'cancelled' => 'Your trip is cancelled. A confirmation is on its way to your email.',
        default => null,
    };
    $facts = [
        ['calendar-days', 'Date', $start->format('l, F j, Y')],
        ['clock', 'Time', $start->format('g:i A').' – '.$end->format('g:i A').' ('.$trip->hours.' '.Str::plural('hour', $trip->hours).')'],
        ['users', 'Guests', $trip->guests.' '.Str::plural('guest', $trip->guests)],
        ['party-popper', 'Occasion', $trip->occasion ?? '—'],
    ];
@endphp

@section('content')
    <x-account.shell active="trips" :heading="$trip->experience?->name ?? 'Private charter'" :back="['All trips', route('account.trips.index')]">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <span class="rounded-full px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wider {{ $class }}">{{ $label }}</span>
                <span class="font-mono text-sm font-bold tracking-widest text-navy-500">{{ $trip->reference }}</span>
            </div>
        </x-slot:actions>

        @if ($flash)
            <p class="mt-6 flex items-start gap-3 rounded-2xl bg-emerald-50 p-4 text-sm font-semibold text-emerald-800 ring-1 ring-emerald-200" role="status"><i data-lucide="circle-check" class="mt-0.5 h-4 w-4 shrink-0"></i>{{ $flash }}</p>
        @endif
        <div class="mt-8 grid items-start gap-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                {{-- Trip summary --}}
                <section class="overflow-hidden rounded-3xl bg-white shadow-card ring-1 ring-navy-100" aria-label="Trip details">
                    <div class="relative aspect-[16/8] bg-navy-50">
                        <img src="{{ asset($cover) }}" alt="{{ $boat->title }}" @class(['h-full w-full object-cover', 'grayscale' => $status === App\Enums\BookingStatus::Cancelled])>
                        <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 via-navy-950/10 to-transparent"></div>
                        <div class="absolute inset-x-0 bottom-0 p-5 text-white md:p-6">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brass-300">{{ $boat->category }}{{ $boat->length_ft ? ' · '.$boat->length_ft.' ft' : '' }}</p>
                            <p class="mt-1 font-display text-3xl">{{ $boat->title }}</p>
                        </div>
                    </div>
                    <dl class="grid gap-px bg-navy-100 sm:grid-cols-2">
                        @foreach ($facts as [$icon, $factLabel, $value])
                            <div class="flex gap-3 bg-white p-5">
                                <i data-lucide="{{ $icon }}" class="mt-0.5 h-5 w-5 shrink-0 text-brass-500"></i>
                                <div><dt class="text-xs font-bold uppercase tracking-[0.15em] text-navy-400">{{ $factLabel }}</dt><dd class="mt-1 text-sm font-semibold text-navy-950">{{ $value }}</dd></div>
                            </div>
                        @endforeach
                        @if ($harbor)
                            <div class="flex gap-3 bg-white p-5 sm:col-span-2">
                                <i data-lucide="map-pin" class="mt-0.5 h-5 w-5 shrink-0 text-brass-500"></i>
                                <div class="min-w-0 flex-1">
                                    <dt class="text-xs font-bold uppercase tracking-[0.15em] text-navy-400">Departure</dt>
                                    <dd class="mt-1 text-sm font-semibold text-navy-950">{{ $harbor->name }} <span class="font-normal text-navy-500">· {{ $harbor->area }}</span></dd>
                                    <dd class="mt-1 text-xs text-navy-500">{{ $harbor->notes }} Please arrive 20 minutes early.</dd>
                                </div>
                                <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center gap-1 self-start text-sm font-bold text-brass-600 hover:text-brass-700">Map <i data-lucide="arrow-up-right" class="h-4 w-4"></i></a>
                            </div>
                        @endif
                    </dl>
                </section>

                {{-- Captain --}}
                @if ($status !== App\Enums\BookingStatus::Cancelled)
                    <section class="rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-7" aria-labelledby="captain-title">
                        <h2 id="captain-title" class="text-lg font-extrabold text-navy-950">Your captain</h2>
                        @if ($trip->captain)
                            <div class="mt-4 flex items-center gap-4">
                                <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-navy-950 font-display text-xl text-brass-300">{{ $trip->captain->initials() }}</span>
                                <div>
                                    <p class="font-bold text-navy-950">{{ $trip->captain->name }}</p>
                                    @if ($trip->captain->years_experience)
                                        <p class="text-xs text-navy-500">Licensed USCG captain · {{ $trip->captain->years_experience }} years on Lake Michigan</p>
                                    @endif
                                </div>
                            </div>
                            @if ($upcoming)
                                <p class="mt-4 flex items-start gap-2 rounded-2xl bg-navy-50/70 p-4 text-xs leading-relaxed text-navy-600"><i data-lucide="message-circle" class="h-4 w-4 shrink-0 text-brass-500"></i>Your captain texts you 24 hours before departure with the exact dock and a weather update.</p>
                            @endif
                        @else
                            <p class="mt-3 flex items-start gap-2 text-sm leading-relaxed text-navy-600"><i data-lucide="ship-wheel" class="h-5 w-5 shrink-0 text-brass-500"></i>We're assigning a licensed USCG captain to your trip. You'll get an email with their name as soon as it's confirmed.</p>
                        @endif
                    </section>
                @endif

                {{-- Report a problem (within the complaint window after the trip) --}}
                @if ($canReport)
                    <section id="report" class="scroll-mt-28 rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-7" aria-labelledby="report-title">
                        <h2 id="report-title" class="text-lg font-extrabold text-navy-950">Report a problem</h2>
                        <p class="mt-1 text-sm text-navy-500">Something wasn't right about the boat or the trip? Tell us by {{ $complaintUntil->setTimezone($tz)->format('D, M j \a\t g:i A') }} and our team will look into it within 1 business day.</p>
                        <p class="mt-4 flex items-start gap-2 rounded-2xl bg-navy-50/70 p-4 text-sm text-navy-700"><i data-lucide="message-circle" class="mt-0.5 h-4 w-4 shrink-0 text-brass-500"></i><span>Call {{ $business['phone'] }} or email {{ $business['email'] }} with your reference <span class="whitespace-nowrap">{{ $trip->reference }}</span> and a few words about what happened. Photos help.</span></p>
                    </section>
                @elseif ($status === App\Enums\BookingStatus::Completed)
                    <p class="flex items-start gap-2 rounded-2xl bg-white p-5 text-sm text-navy-500 ring-1 ring-navy-100"><i data-lucide="info" class="h-5 w-5 shrink-0 text-navy-400"></i>The window to report a problem closed on {{ $complaintUntil->setTimezone($tz)->format('M j') }}. Still need help? <a href="{{ route('contact') }}" class="font-bold text-navy-950 underline-offset-2 hover:underline">Contact us</a>.</p>
                @endif
            </div>

            <aside class="space-y-6 lg:self-start lg:sticky lg:top-28 lg:-mx-4 lg:-mt-4 lg:max-h-[calc(100dvh-8rem)] lg:overflow-y-auto lg:px-4 lg:pt-4 lg:pb-4 no-scrollbar" data-sticky-panel>
                {{-- Payment --}}
                <section class="rounded-3xl bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-7" aria-labelledby="payment-title">
                    <h2 id="payment-title" class="text-lg font-extrabold text-navy-950">Payment</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach ($trip->items as $item)
                            <div class="flex justify-between gap-4"><dt class="text-navy-600">{{ $item->label }}<span class="block text-[11px] text-navy-400">{{ $item->description }}</span></dt><dd @class(['font-semibold', 'text-emerald-700' => $item->amount_cents < 0, 'text-navy-900' => $item->amount_cents >= 0])>{{ $item->amount_cents < 0 ? '−'.money(-$item->amount_cents, 'always') : money($item->amount_cents, 'always') }}</dd></div>
                        @endforeach
                        <div class="flex justify-between gap-4 border-t border-navy-100 pt-3 text-base"><dt class="font-bold text-navy-950">Total</dt><dd class="font-extrabold text-navy-950">{{ money($trip->total_cents, 'always') }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-navy-600">Paid · {{ $card }}</dt><dd class="font-semibold text-emerald-700">{{ money($trip->amount_paid_cents, 'always') }}</dd></div>
                        @if ($trip->amount_refunded_cents > 0)
                            <div class="flex justify-between gap-4"><dt class="text-navy-600">Refunded</dt><dd class="font-semibold text-red-600">−{{ money($trip->amount_refunded_cents, 'always') }}</dd></div>
                        @endif
                        @foreach ($trip->refunds->whereIn('status', [App\Enums\RefundStatus::Requested, App\Enums\RefundStatus::Pending]) as $open)
                            <div class="flex justify-between gap-4"><dt class="text-navy-600">{{ $open->status === App\Enums\RefundStatus::Requested ? 'Refund under review' : 'Refund on its way' }}</dt><dd class="font-semibold text-navy-700">{{ money($open->amount_cents, 'always') }}</dd></div>
                        @endforeach
                    </dl>
                    <div class="mt-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                        <button type="button" data-soon class="inline-flex items-center justify-center gap-2 rounded-full border border-navy-200 px-4 py-2.5 text-sm font-bold text-navy-900 transition hover:border-navy-950 hover:bg-navy-950 hover:text-white"><i data-lucide="file-text" class="h-4 w-4"></i>Receipt</button>
                        @if ($upcoming)
                            <a href="{{ route('bookings.calendar', $trip) }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-navy-950 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-navy-800"><i data-lucide="calendar-plus" class="h-4 w-4"></i>Add to calendar</a>
                        @endif
                    </div>
                </section>

                {{-- Cancellation --}}
                <section class="rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-7" aria-labelledby="cancel-title" id="cancellation">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="cancel-title" class="text-lg font-extrabold text-navy-950">Cancellation</h2>
                        @if (! empty($policy['name']))
                            <span class="rounded-full bg-navy-100 px-2.5 py-1 text-[11px] font-bold text-navy-700">{{ $policy['name'] }} policy</span>
                        @endif
                    </div>
                    @if (! empty($policy['points']))
                        <ul class="mt-3 list-disc space-y-1.5 pl-5 text-xs leading-relaxed text-navy-500">
                            @foreach ($policy['points'] as $point)
                                <li>{{ $point }}</li>
                            @endforeach
                        </ul>
                    @endif

                    @if ($status === App\Enums\BookingStatus::Cancelled)
                        <div class="mt-5 space-y-2 rounded-2xl bg-red-50 p-4 text-sm text-red-900 ring-1 ring-red-200">
                            <p class="font-bold">Cancelled{{ $trip->cancelled_at ? ' on '.$trip->cancelled_at->setTimezone($tz)->format('M j, Y') : '' }}.</p>
                            @forelse ($trip->refunds as $item)
                                <p>{{ $refundLine($item) }}</p>
                            @empty
                                <p>{{ $trip->amount_refunded_cents > 0 ? money($trip->amount_refunded_cents, 'always').' was refunded to '.$card.'.' : 'No refund was due under this policy.' }}</p>
                            @endforelse
                        </div>
                    @elseif ($refund)
                        <div class="mt-5 rounded-2xl bg-navy-50/70 p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.15em] text-navy-400">If you cancel now</p>
                            <p class="mt-1 text-2xl font-extrabold text-navy-950">{{ money($refund['refund_cents'], 'always') }} <span class="text-sm font-semibold text-navy-500">back to your card</span></p>
                            <p class="mt-1 text-xs text-navy-500">{{ $refund['percent'] }}% refund under this policy (the crew gratuity always comes back){{ $refund['fee_cents'] > 0 ? ', less the '.money($refund['fee_cents'], 'always').' card processing fee, which isn\'t refundable' : '' }}.</p>
                            @if ($refund['next'])
                                <p class="mt-2 text-xs font-semibold text-amber-800">After {{ $refund['next']['at']->setTimezone($tz)->format('M j, g:i A') }}: {{ $refund['next']['percent'] }}% refund.</p>
                            @endif
                        </div>
                        <button type="button" data-open-cancel class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full border border-red-200 px-6 py-3 text-sm font-bold text-red-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white">Cancel trip</button>
                        <p class="mt-3 text-xs leading-relaxed text-navy-500">Your time is freed right away. Our team reviews the refund, usually within 1 business day, and emails you when it's sent.</p>
                    @else
                        <p class="mt-5 text-xs text-navy-500">This trip has ended.</p>
                    @endif
                    <a href="{{ route('legal.cancellation') }}" class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-brass-600 hover:text-brass-700">Full cancellation policy <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                </section>
            </aside>
        </div>
    </x-account.shell>
@endsection

@if ($refund)
    @push('modals')
        <dialog id="cancel-dialog" aria-labelledby="cancel-dialog-title" @if ($errors->has('reason') || $errors->has('note') || $errors->has('cancel')) data-open-on-load @endif class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl bg-white p-0 text-navy-900 shadow-2xl backdrop:bg-navy-950/60">
            <form id="cancel-form" method="POST" action="{{ route('account.trips.cancel', $trip) }}" class="p-6 md:p-8">
                @csrf
                <input type="hidden" name="idempotency_key" value="{{ Str::uuid() }}">
                <h2 id="cancel-dialog-title" class="font-display text-3xl text-navy-950">Cancel this trip?</h2>
                <p class="mt-3 text-sm leading-relaxed text-navy-600">{{ $trip->experience?->name ?? 'Your private charter' }} on {{ $start->format('D, M j') }}. Under the {{ $policy['name'] ?? '' }} policy you get <strong class="text-navy-950">{{ money($refund['refund_cents'], 'always') }}</strong> back to {{ $card }} ({{ $refund['percent'] }}%{{ $refund['fee_cents'] > 0 ? ', less the '.money($refund['fee_cents'], 'always').' card fee' : '' }}). Our team reviews it, usually within 1 business day; refunds then take 5–10 business days to appear.</p>
                @error('cancel')
                    <p class="mt-4 rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-700 ring-1 ring-red-200" role="alert">{{ $message }}</p>
                @enderror
                <x-form.select name="reason" label="Why are you cancelling?" class="mt-5" required placeholder="Choose a reason" :options="$cancelReasons" />
                <x-form.textarea name="note" label="Anything we should know? (optional)" class="mt-4" rows="2" maxlength="500" />
                <p class="mt-3 text-xs text-navy-400">Worried about the weather? If we cancel for weather or safety, you get a full refund (the card processing fee isn't refundable).</p>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button type="button" variant="outline" data-close-dialog>Keep my trip</x-button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-red-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-red-700">Cancel trip</button>
                </div>
            </form>
        </dialog>
    @endpush
@endif
