@extends('layouts.app', [
    'title' => 'Trip '.$trip->ref,
    'page' => 'account-trip',
    'headerSolid' => true,
    'robots' => 'noindex',
    'mainClass' => 'min-h-screen pb-24 pt-28',
])

@php
    $harbor = $trip->yacht->harbor;
    $q = $trip->quote;
    $mapUrl = 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($harbor->map_query);
    $ics = $trip->is_upcoming ? [
        'ref' => $trip->ref,
        'domain' => $business['domain'],
        'start' => $trip->starts_at->format('Ymd\THis'),
        'end' => $trip->ends_at->format('Ymd\THis'),
        'summary' => $trip->experience->name.' on '.$trip->yacht->title,
        'location' => $harbor->name.', Chicago, IL',
        'description' => 'Booking '.$trip->ref.' · '.$trip->guests.' guests · Arrive 20 minutes early. Questions: '.$business['phone'],
    ] : null;
    $facts = [
        ['calendar-days', 'Date', $trip->starts_at->format('l, F j, Y')],
        ['clock', 'Time', $trip->starts_at->format('g:i A').' – '.$trip->ends_at->format('g:i A').' ('.$trip->hours.' hours)'],
        ['users', 'Guests', $trip->guests.' '.Str::plural('guest', $trip->guests)],
        ['party-popper', 'Occasion', $trip->occasion ?? '—'],
    ];
    $refund = $trip->refund;
@endphp

@section('content')
    <x-account.shell active="trips" :heading="$trip->experience->name" :back="['All trips', route('account.trips.index')]">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <span class="rounded-full px-2.5 py-1 text-[11px] font-extrabold uppercase tracking-wider {{ $trip->status_class }}">{{ $trip->status_label }}</span>
                <span class="font-mono text-sm font-bold tracking-widest text-navy-500">{{ $trip->ref }}</span>
            </div>
        </x-slot:actions>

        <div class="mt-8 grid items-start gap-6 lg:grid-cols-[minmax(0,1.45fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                {{-- Trip summary --}}
                <section class="overflow-hidden rounded-3xl bg-white shadow-card ring-1 ring-navy-100" aria-label="Trip details">
                    <div class="relative aspect-[16/8] bg-navy-50">
                        <img src="{{ asset($trip->yacht->cover) }}" alt="{{ $trip->yacht->title }}" @class(['h-full w-full object-cover', 'grayscale' => $trip->status === 'cancelled'])>
                        <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 via-navy-950/10 to-transparent"></div>
                        <div class="absolute inset-x-0 bottom-0 p-5 text-white md:p-6">
                            <p class="text-xs font-bold uppercase tracking-[0.2em] text-brass-300">{{ $trip->yacht->category }} · {{ $trip->yacht->length_ft }} ft</p>
                            <p class="mt-1 font-display text-3xl">{{ $trip->yacht->title }}</p>
                        </div>
                    </div>
                    <dl class="grid gap-px bg-navy-100 sm:grid-cols-2">
                        @foreach ($facts as [$icon, $label, $value])
                            <div class="flex gap-3 bg-white p-5">
                                <i data-lucide="{{ $icon }}" class="mt-0.5 h-5 w-5 shrink-0 text-brass-500"></i>
                                <div><dt class="text-xs font-bold uppercase tracking-[0.15em] text-navy-400">{{ $label }}</dt><dd class="mt-1 text-sm font-semibold text-navy-950">{{ $value }}</dd></div>
                            </div>
                        @endforeach
                        <div class="flex gap-3 bg-white p-5 sm:col-span-2">
                            <i data-lucide="map-pin" class="mt-0.5 h-5 w-5 shrink-0 text-brass-500"></i>
                            <div class="min-w-0 flex-1">
                                <dt class="text-xs font-bold uppercase tracking-[0.15em] text-navy-400">Departure</dt>
                                <dd class="mt-1 text-sm font-semibold text-navy-950">{{ $harbor->name }} <span class="font-normal text-navy-500">· {{ $harbor->area }}</span></dd>
                                <dd class="mt-1 text-xs text-navy-500">{{ $harbor->notes }} Please arrive 20 minutes early.</dd>
                            </div>
                            <a href="{{ $mapUrl }}" target="_blank" rel="noopener" class="inline-flex shrink-0 items-center gap-1 self-start text-sm font-bold text-brass-600 hover:text-brass-700">Map <i data-lucide="arrow-up-right" class="h-4 w-4"></i></a>
                        </div>
                    </dl>
                </section>

                {{-- Captain --}}
                @if ($trip->status !== 'cancelled')
                    <section class="rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-7" aria-labelledby="captain-title">
                        <h2 id="captain-title" class="text-lg font-extrabold text-navy-950">Your captain</h2>
                        @if ($trip->captain)
                            <div class="mt-4 flex items-center gap-4">
                                <span class="grid h-14 w-14 shrink-0 place-items-center rounded-full bg-navy-950 font-display text-xl text-brass-300">{{ $trip->captain->initials }}</span>
                                <div>
                                    <p class="font-bold text-navy-950">{{ $trip->captain->name }}</p>
                                    <p class="text-xs text-navy-500">{{ $trip->captain->license }} · {{ $trip->captain->years }} years on Lake Michigan</p>
                                </div>
                            </div>
                            @if ($trip->is_upcoming)
                                <p class="mt-4 flex items-start gap-2 rounded-2xl bg-navy-50/70 p-4 text-xs leading-relaxed text-navy-600"><i data-lucide="message-circle" class="h-4 w-4 shrink-0 text-brass-500"></i>Your captain texts you 24 hours before departure with the exact dock and a weather update.</p>
                            @endif
                        @else
                            <p class="mt-3 flex items-start gap-2 text-sm leading-relaxed text-navy-600"><i data-lucide="ship-wheel" class="h-5 w-5 shrink-0 text-brass-500"></i>We're assigning a licensed USCG captain to your trip. You'll get an email with their name as soon as it's confirmed.</p>
                        @endif
                    </section>
                @endif

                {{-- Report a problem (72 hours after the trip) --}}
                @if ($trip->can_complain)
                    <section id="report" class="scroll-mt-28 rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-7" aria-labelledby="report-title">
                        <h2 id="report-title" class="text-lg font-extrabold text-navy-950">Report a problem</h2>
                        <p class="mt-1 text-sm text-navy-500">Something wasn't right about the boat or the trip? Tell us by {{ $trip->complaint_until->format('D, M j \a\t g:i A') }} and our team will look into it within 1 business day.</p>
                        <form class="mt-5 space-y-5" id="report-form" novalidate data-max-photos="5">
                            <x-form.select name="category" label="What went wrong?" :options="$categories" placeholder="Choose a category" required />
                            <x-form.textarea name="description" label="Describe what happened" rows="5" maxlength="2000" required placeholder="What happened, and when? The more detail, the faster we can help." />
                            <div>
                                <label for="report-photos" class="text-sm font-bold text-navy-800">Photos <span class="font-normal text-navy-400">(optional, up to 5)</span></label>
                                <input id="report-photos" type="file" accept="image/jpeg,image/png,image/webp" multiple class="mt-2 block w-full rounded-xl border border-dashed border-navy-200 bg-navy-50/50 p-3 text-sm text-navy-600 file:mr-4 file:rounded-full file:border-0 file:bg-navy-950 file:px-4 file:py-2 file:text-xs file:font-bold file:text-white">
                                <p class="mt-1.5 text-xs text-navy-400">JPG, PNG or WEBP, up to 10 MB each. Only our team can see them.</p>
                            </div>
                            <x-button type="submit" variant="dark">Send report</x-button>
                        </form>
                    </section>
                @elseif ($trip->status === 'completed')
                    <p class="flex items-start gap-2 rounded-2xl bg-white p-5 text-sm text-navy-500 ring-1 ring-navy-100"><i data-lucide="info" class="h-5 w-5 shrink-0 text-navy-400"></i>The window to report a problem closed on {{ $trip->complaint_until->format('M j') }}. Still need help? <a href="{{ route('contact') }}" class="font-bold text-navy-950 underline-offset-2 hover:underline">Contact us</a>.</p>
                @endif
            </div>

            <aside class="space-y-6 lg:sticky lg:top-28">
                {{-- Payment --}}
                <section class="rounded-3xl bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-7" aria-labelledby="payment-title">
                    <h2 id="payment-title" class="text-lg font-extrabold text-navy-950">Payment</h2>
                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach ($q['lines'] as $line)
                            <div class="flex justify-between gap-4"><dt class="text-navy-600">{{ $line['label'] }}<span class="block text-[11px] text-navy-400">{{ $line['sub'] }}</span></dt><dd class="font-semibold text-navy-900">{{ money($line['amount'], 'always') }}</dd></div>
                        @endforeach
                        <div class="flex justify-between gap-4"><dt class="text-navy-600">Taxes</dt><dd class="font-semibold text-navy-900">{{ money($q['tax'], 'always') }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-navy-600">Crew gratuity ({{ $q['gratuityPct'] }}%)</dt><dd class="font-semibold text-navy-900">{{ money($q['gratuity'], 'always') }}</dd></div>
                        <div class="flex justify-between gap-4 border-t border-navy-100 pt-3 text-base"><dt class="font-bold text-navy-950">Total</dt><dd class="font-extrabold text-navy-950">{{ money($q['total'], 'always') }}</dd></div>
                        <div class="flex justify-between gap-4"><dt class="text-navy-600">Paid · {{ $trip->card }}</dt><dd class="font-semibold text-emerald-700">{{ money($trip->paid_cents, 'always') }}</dd></div>
                        @if ($trip->balance_cents > 0)
                            <div class="flex justify-between gap-4"><dt class="text-navy-600">Balance · charged {{ $trip->balance_due->format('M j') }}</dt><dd class="font-semibold text-navy-900">{{ money($trip->balance_cents, 'always') }}</dd></div>
                        @endif
                        @if ($trip->refunded_cents > 0)
                            <div class="flex justify-between gap-4"><dt class="text-navy-600">Refunded</dt><dd class="font-semibold text-red-600">−{{ money($trip->refunded_cents, 'always') }}</dd></div>
                        @endif
                    </dl>
                    <div class="mt-6 grid gap-2 sm:grid-cols-2 lg:grid-cols-1">
                        <button type="button" data-soon class="inline-flex items-center justify-center gap-2 rounded-full border border-navy-200 px-4 py-2.5 text-sm font-bold text-navy-900 transition hover:border-navy-950 hover:bg-navy-950 hover:text-white"><i data-lucide="file-text" class="h-4 w-4"></i>Receipt</button>
                        @if ($ics)
                            <button type="button" id="ics-btn" data-ics="{{ json_encode($ics) }}" class="inline-flex items-center justify-center gap-2 rounded-full bg-navy-950 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-navy-800"><i data-lucide="calendar-plus" class="h-4 w-4"></i>Add to calendar</button>
                        @endif
                    </div>
                </section>

                {{-- Cancellation --}}
                <section class="rounded-3xl bg-white p-6 shadow-xs ring-1 ring-navy-100 md:p-7" aria-labelledby="cancel-title" id="cancellation">
                    <div class="flex items-center justify-between gap-3">
                        <h2 id="cancel-title" class="text-lg font-extrabold text-navy-950">Cancellation</h2>
                        <span class="rounded-full bg-navy-100 px-2.5 py-1 text-[11px] font-bold text-navy-700">{{ $policy['name'] }} policy</span>
                    </div>
                    <ul class="mt-3 list-disc space-y-1.5 pl-5 text-xs leading-relaxed text-navy-500">
                        @foreach ($policy['points'] as $point)
                            <li>{{ $point }}</li>
                        @endforeach
                    </ul>

                    @if ($trip->status === 'cancelled')
                        <p class="mt-5 rounded-2xl bg-red-50 p-4 text-sm text-red-900 ring-1 ring-red-200">Cancelled on {{ $trip->cancelled_at->format('M j, Y') }}. {{ money($trip->refunded_cents, 'always') }} was refunded to {{ $trip->card }}.</p>
                    @elseif ($trip->can_cancel)
                        <div class="mt-5 rounded-2xl bg-navy-50/70 p-4" data-cancel-state>
                            <p class="text-xs font-bold uppercase tracking-[0.15em] text-navy-400">If you cancel now</p>
                            <p class="mt-1 text-2xl font-extrabold text-navy-950">{{ money($refund['refund'], 'always') }} <span class="text-sm font-semibold text-navy-500">back to your card</span></p>
                            <p class="mt-1 text-xs text-navy-500">{{ $refund['percent'] }}% refund under this policy{{ $refund['cancelled_balance'] > 0 ? ' · the '.money($refund['cancelled_balance'], 'always').' balance is never charged' : '' }}.</p>
                            @if ($trip->next_drop)
                                <p class="mt-2 text-xs font-semibold text-amber-800">After {{ $trip->next_drop['at']->format('M j, g:i A') }}: {{ $trip->next_drop['percent'] }}% refund.</p>
                            @endif
                        </div>
                        <button type="button" data-open-cancel class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-full border border-red-200 px-6 py-3 text-sm font-bold text-red-700 transition hover:border-red-600 hover:bg-red-600 hover:text-white">Cancel trip</button>
                    @else
                        <p class="mt-5 text-xs text-navy-500">This trip has ended.</p>
                    @endif
                    <a href="{{ route('legal.cancellation') }}" class="mt-4 inline-flex items-center gap-1 text-sm font-bold text-brass-600 hover:text-brass-700">Full cancellation policy <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                </section>
            </aside>
        </div>
    </x-account.shell>
@endsection

@if ($trip->can_cancel)
    @push('modals')
        <dialog id="cancel-dialog" aria-labelledby="cancel-dialog-title" class="m-auto w-[calc(100%-2rem)] max-w-lg rounded-3xl bg-white p-0 text-navy-900 shadow-2xl backdrop:bg-navy-950/60">
            <form id="cancel-form" class="p-6 md:p-8" novalidate>
                <h2 id="cancel-dialog-title" class="font-display text-3xl text-navy-950">Cancel this trip?</h2>
                <p class="mt-3 text-sm leading-relaxed text-navy-600">{{ $trip->experience->name }} on {{ $trip->starts_at->format('D, M j') }}. You'll get <strong class="text-navy-950">{{ money($refund['refund'], 'always') }}</strong> back to {{ $trip->card }} ({{ $refund['percent'] }}%){{ $refund['cancelled_balance'] > 0 ? ', and the remaining balance is never charged' : '' }}. Refunds take 5–10 business days to appear.</p>
                <x-form.select name="reason" label="Why are you cancelling?" class="mt-5" required placeholder="Choose a reason" :options="['plans' => 'Change of plans', 'date' => 'Need a different date', 'weather' => 'Worried about the weather', 'price' => 'Found a better price', 'other' => 'Something else']" />
                <p class="mt-3 text-xs text-navy-400">Worried about the weather? If your captain cancels for weather or safety, you get a full refund or a free new date.</p>
                <div class="mt-6 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-button type="button" variant="outline" data-close-dialog>Keep my trip</x-button>
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-full bg-red-600 px-6 py-3 text-sm font-bold text-white transition hover:bg-red-700">Cancel trip</button>
                </div>
            </form>
        </dialog>
    @endpush
@endif
