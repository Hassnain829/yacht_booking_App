@extends('layouts.app', [
    'title' => 'Book your charter',
    'description' => 'Book a private Chicago yacht charter online: choose your experience, yacht, date and departure time. Captain included, instant confirmation.',
    'page' => 'booking',
    'mobileBar' => true,
    'mainClass' => 'pb-28 lg:pb-0',
])

@section('content')
    <section class="relative isolate overflow-hidden bg-navy-950 pb-28 pt-36 text-white md:pt-40">
        <img src="{{ asset('images/wake.jpg') }}" alt="" class="absolute inset-0 -z-20 h-full w-full object-cover opacity-25">
        <div class="absolute inset-0 -z-10 bg-gradient-to-b from-navy-950/70 to-navy-950"></div>
        <div class="container">
            <div class="flex flex-col justify-between gap-6 md:flex-row md:items-end">
                <div>
                    <p class="flex items-center gap-3 text-xs font-bold uppercase tracking-[0.3em] text-brass-300"><span class="h-px w-10 bg-brass-400" aria-hidden="true"></span>Online booking</p>
                    <h1 class="mt-4 font-display text-4xl md:text-6xl">Book your charter</h1>
                    <p class="mt-3 text-white/60">Pick a date. Pick a boat. Set sail.</p>
                </div>
                <ul class="flex flex-wrap gap-x-6 gap-y-2 text-xs font-semibold text-white/70">
                    <li class="flex items-center gap-2"><i data-lucide="zap" class="h-4 w-4 text-brass-300"></i>Instant confirmation</li>
                    <li class="flex items-center gap-2"><i data-lucide="lock" class="h-4 w-4 text-brass-300"></i>Secure checkout</li>
                    <li class="flex items-center gap-2"><i data-lucide="ship-wheel" class="h-4 w-4 text-brass-300"></i>Captain included</li>
                </ul>
            </div>
            <ol id="stepper" class="mt-10 grid grid-cols-4 gap-2 md:gap-4" aria-label="Booking steps"></ol>
        </div>
    </section>

    <section class="relative z-10 -mt-16 pb-24">
        {{-- resources/js/pages/booking.js renders the steps from this data (display data only — the server re-checks everything). --}}
        <div class="container grid gap-8 lg:grid-cols-[minmax(0,1fr)_380px]" id="booking-wizard" data-wizard="{{ json_encode($wizard) }}">
            <div id="step-container" class="min-w-0 scroll-mt-28" aria-live="polite">
                <noscript>
                    <div class="rounded-[2rem] bg-white p-8 text-center shadow-card ring-1 ring-navy-100">
                        <p class="font-display text-2xl text-navy-950">Online booking needs JavaScript</p>
                        <p class="mt-2 text-sm text-navy-500">Please enable JavaScript, or call us at {{ config('site.business.phone') }} and we'll book for you.</p>
                    </div>
                </noscript>
            </div>
            <aside class="hidden lg:block"><div id="summary" class="sticky top-28"></div></aside>
        </div>
    </section>
@endsection

@push('modals')
    {{-- Mobile summary bar --}}
    <div id="mobile-summary" class="fixed inset-x-0 bottom-0 z-40 lg:hidden"></div>
@endpush
