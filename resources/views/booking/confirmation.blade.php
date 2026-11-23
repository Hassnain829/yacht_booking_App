@extends('layouts.app', [
    'title' => 'Booking confirmed',
    'page' => 'confirmation',
    'robots' => 'noindex', // one visitor's booking (3.6)
    'mainClass' => 'pb-24',
])

@php
    $business = config('site.business');
    $harbor = $yacht->harbor;
    $start = \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:i', $selection->date.' '.$selection->time, config('app.display_timezone'));
    $end = $start->addHours($selection->hours);
    $whole = fn (int $cents) => money($cents, 'never');
    $btnDark = 'inline-flex items-center justify-center gap-2 rounded-full bg-navy-950 px-6 py-3 text-sm font-bold text-white transition hover:-translate-y-0.5 hover:bg-navy-800';
    $btnOutline = 'inline-flex items-center justify-center gap-2 rounded-full border border-navy-200 px-6 py-3 text-sm font-bold text-navy-900 transition hover:border-navy-950 hover:bg-navy-950 hover:text-white';
    $next = [
        ['mail-check', 'Confirmation email', 'Your receipt, booking details and waiver link are on the way to your inbox.'],
        ['users', 'Share with your crew', 'Forward the waiver link so every guest can sign before boarding.'],
        ['message-circle', 'Your captain reaches out', 'Expect a text 24 hours before departure with the exact dock and weather update.'],
        ['anchor', 'Arrive & set sail', 'Check in at '.$harbor?->name.' 20 minutes early. Bring ID, sunscreen and your playlist.'],
    ];
    $ics = [
        'ref' => $c['ref'],
        'domain' => $business['domain'],
        'start' => $start->format('Ymd\THis'),
        'end' => $end->format('Ymd\THis'),
        'summary' => $experience->name.' on '.$yacht->title,
        'location' => $harbor?->name.', Chicago, IL',
        'description' => 'Booking '.$c['ref'].' · '.$selection->guests.' guests · Arrive 20 minutes early. Questions: '.$business['phone'],
    ];
@endphp

@section('content')
    <section class="relative isolate overflow-hidden bg-navy-950 pb-36 pt-40 text-center text-white">
        <img src="{{ asset($yacht->cover) }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover opacity-30">
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-navy-950/60 to-navy-950"></div>
        <div class="container">
            @if ($isDemo)
                <p class="no-print mx-auto mb-6 inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-xs font-bold text-white/80"><i data-lucide="info" class="h-4 w-4 text-brass-300"></i>Sample confirmation for the demo</p>
            @endif
            <span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-brass-400 text-navy-950 shadow-glow"><i data-lucide="check" class="h-10 w-10"></i></span>
            <h1 class="mt-6 font-display text-4xl md:text-6xl">You're booked, {{ $c['contact']['first_name'] }}!</h1>
            <p class="mx-auto mt-4 max-w-xl text-white/70">A confirmation has been sent to <strong class="text-white">{{ $c['contact']['email'] }}</strong>. We can't wait to welcome you aboard {{ $yacht->title }}.</p>
            <p class="mt-6 inline-flex items-center gap-3 rounded-full border border-white/15 bg-white/5 px-5 py-2.5 text-sm">
                <span class="text-white/60">Booking reference</span><strong class="font-mono tracking-widest text-brass-300" id="booking-ref">{{ $c['ref'] }}</strong>
                <button type="button" id="copy-ref" class="no-print text-white/60 hover:text-white" aria-label="Copy reference"><i data-lucide="copy" class="h-4 w-4"></i></button>
            </p>
        </div>
    </section>

    <section class="relative z-10 -mt-24">
        <div class="container grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px]">
            <div class="space-y-6">
                <div class="overflow-hidden rounded-[2rem] bg-white shadow-card ring-1 ring-navy-100">
                    <div class="flex flex-col gap-5 p-6 sm:flex-row sm:items-center md:p-8">
                        <img src="{{ asset($yacht->cover) }}" alt="{{ $yacht->title }}" class="h-28 w-full rounded-2xl object-cover sm:w-40">
                        <div class="flex-1">
                            <p class="text-[11px] font-bold uppercase tracking-[0.2em] text-brass-600">{{ $experience->name }}</p>
                            <h2 class="mt-1 font-display text-3xl text-navy-950">{{ $yacht->title }}</h2>
                            <p class="mt-1 text-sm text-navy-500">{{ $yacht->length_ft }} ft {{ $yacht->category }} · {{ $yacht->crew_count > 1 ? 'Captain + crew' : 'Licensed captain' }} included</p>
                        </div>
                        <span class="inline-flex items-center gap-1.5 self-start rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200"><i data-lucide="circle-check" class="h-4 w-4"></i>Confirmed</span>
                    </div>
                    <dl class="grid grid-cols-2 gap-px border-t border-navy-100 bg-navy-100 md:grid-cols-4">
                        <div class="bg-white p-5"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Date</dt><dd class="mt-1 font-bold text-navy-950">{{ $start->format('D, F j') }}</dd></div>
                        <div class="bg-white p-5"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Time</dt><dd class="mt-1 font-bold text-navy-950">{{ $start->format('g:i A') }} – {{ $end->format('g:i A') }}</dd></div>
                        <div class="bg-white p-5"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Guests</dt><dd class="mt-1 font-bold text-navy-950">{{ $selection->guests }}</dd></div>
                        <div class="bg-white p-5"><dt class="text-[10px] font-bold uppercase tracking-[0.18em] text-navy-400">Duration</dt><dd class="mt-1 font-bold text-navy-950">{{ $selection->hours }} hours</dd></div>
                    </dl>
                    @if ($addons || $c['occasion'])
                        <div class="flex flex-wrap gap-2 border-t border-navy-100 p-6 md:px-8">
                            @if ($c['occasion'])<span class="rounded-full bg-brass-50 px-3 py-1.5 text-xs font-bold text-brass-800 ring-1 ring-brass-200">{{ $c['occasion'] }}</span>@endif
                            @foreach ($addons as $addon)<span class="rounded-full bg-navy-50 px-3 py-1.5 text-xs font-bold text-navy-700">+ {{ $addon->name }}</span>@endforeach
                        </div>
                    @endif
                    <div class="no-print flex flex-wrap gap-3 border-t border-navy-100 bg-navy-50/50 p-6 md:px-8">
                        <button type="button" id="ics-btn" data-ics="{{ json_encode($ics) }}" class="{{ $btnDark }}"><i data-lucide="calendar-plus" class="h-4 w-4"></i>Add to calendar</button>
                        <button type="button" id="print-btn" class="{{ $btnOutline }}"><i data-lucide="printer" class="h-4 w-4"></i>Print</button>
                        @if ($harbor)
                            <a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($harbor->map_query) }}" target="_blank" rel="noopener noreferrer" class="{{ $btnOutline }}"><i data-lucide="navigation" class="h-4 w-4"></i>Directions</a>
                        @endif
                    </div>
                </div>

                <div class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100 md:p-8">
                    <h3 class="font-display text-2xl text-navy-950">What happens next</h3>
                    <ol class="mt-6 space-y-6">
                        @foreach ($next as [$icon, $title, $text])
                            <li class="relative flex gap-4">
                                @unless ($loop->last)<span class="absolute left-5 top-11 h-[calc(100%-1rem)] w-px bg-navy-100" aria-hidden="true"></span>@endunless
                                <span @class(['relative grid h-10 w-10 shrink-0 place-items-center rounded-full', 'bg-brass-400 text-navy-950' => $loop->first, 'bg-navy-50 text-navy-600' => ! $loop->first])><i data-lucide="{{ $icon }}" class="h-5 w-5"></i></span>
                                <div><p class="font-bold text-navy-950">{{ $title }}</p><p class="mt-0.5 text-sm text-navy-500">{{ $text }}</p></div>
                            </li>
                        @endforeach
                    </ol>
                </div>

                @if ($harbor)
                    <div class="overflow-hidden rounded-[2rem] bg-white shadow-card ring-1 ring-navy-100">
                        <div class="p-6 md:p-8">
                            <h3 class="font-display text-2xl text-navy-950">Meeting point: {{ $harbor->name }}</h3>
                            <p class="mt-1 text-sm text-navy-500">{{ $harbor->area }}. {{ $harbor->notes }}</p>
                        </div>
                        <iframe title="Map of {{ $harbor->name }}" src="https://www.google.com/maps?q={{ urlencode($harbor->map_query) }}&amp;output=embed" class="h-64 w-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    </div>
                @endif
            </div>

            <aside class="space-y-6">
                <div class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100">
                    <h3 class="text-lg font-extrabold text-navy-950">Payment summary</h3>
                    <dl class="mt-4 space-y-2.5 text-sm">
                        <div class="flex justify-between"><dt class="text-navy-500">Booking total</dt><dd class="font-bold text-navy-950">{{ $whole($c['totals']['total']) }}</dd></div>
                        <div class="flex justify-between"><dt class="text-navy-500">Paid today</dt><dd class="font-bold text-emerald-700">{{ $whole($c['totals']['paid']) }}</dd></div>
                        @if ($c['totals']['balance'] > 0)
                            <div class="flex justify-between"><dt class="text-navy-500">Balance due{{ $c['totals']['balance_date'] ? ' '.\Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $c['totals']['balance_date'])->format('M j') : '' }}</dt><dd class="font-bold text-navy-950">{{ $whole($c['totals']['balance']) }}</dd></div>
                        @endif
                        <div class="flex justify-between border-t border-navy-100 pt-3"><dt class="text-navy-500">Card</dt><dd class="font-semibold text-navy-700">{{ $c['payment']['brand'] }} •••• {{ $c['payment']['last4'] }}</dd></div>
                        <div class="flex justify-between"><dt class="text-navy-500">Gratuity</dt><dd class="font-semibold text-navy-700">{{ $c['payment']['gratuity'] }}%</dd></div>
                        @if ($c['payment']['promo'])
                            <div class="flex justify-between"><dt class="text-navy-500">Promo</dt><dd class="font-semibold text-emerald-700">{{ $c['payment']['promo'] }}</dd></div>
                        @endif
                    </dl>
                </div>

                <div class="rounded-[2rem] bg-navy-950 p-6 text-white">
                    <h3 class="text-lg font-extrabold">What to bring</h3>
                    <ul class="mt-4 space-y-2.5 text-sm text-white/75">
                        @foreach (['Photo ID (21+ for BYOB)', 'Sunscreen & sunglasses', 'Soft-soled shoes', 'A light layer for the evening', 'Drinks & snacks (no glass)'] as $item)
                            <li class="flex items-center gap-2.5"><i data-lucide="check" class="h-4 w-4 text-brass-300"></i>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>

                <div class="rounded-[2rem] bg-white p-6 shadow-card ring-1 ring-navy-100">
                    <h3 class="text-lg font-extrabold text-navy-950">Need to change something?</h3>
                    <p class="mt-2 text-sm text-navy-500">Free date changes up to 14 days before your charter.</p>
                    <div class="mt-4 space-y-2 text-sm">
                        <a href="tel:{{ $business['phone_href'] }}" class="flex items-center gap-2 font-bold text-navy-900 hover:text-brass-600"><i data-lucide="phone" class="h-4 w-4 text-brass-500"></i>{{ $business['phone'] }}</a>
                        <a href="mailto:{{ $business['email'] }}" class="flex items-center gap-2 font-bold text-navy-900 hover:text-brass-600"><i data-lucide="mail" class="h-4 w-4 text-brass-500"></i>{{ $business['email'] }}</a>
                    </div>
                </div>

                <x-button :href="route('home')" size="block" class="no-print">Back to home</x-button>
            </aside>
        </div>
    </section>
@endsection
