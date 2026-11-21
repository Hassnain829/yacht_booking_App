{{-- Phase 1.1 QA page: every shared component and state on one page. Local environment only. --}}
@extends('layouts.app', ['title' => 'Component gallery', 'page' => 'dev-components'])

@php
    // Show the "Unavailable" state on one demo boat (PLAN D12) without changing the demo data.
    $cards = $yachts->take(3)->values();
    $cards[2] = new Illuminate\Support\Fluent(array_merge($cards[2]->toArray(), ['booking_enabled' => false]));
@endphp

@section('content')
    <x-page-hero image="images/chicago-river-night.jpg" crumb="Components" intro="Every shared Blade component and state from Phase 1.1, rendered with the real layout.">
        Component <em>gallery</em>
    </x-page-hero>

    {{-- Buttons --}}
    <section class="container py-20">
        <x-section-heading eyebrow="Buttons" subtitle="primary · dark · outline (light backgrounds) — sizes sm, md, lg, xl">
            Call to <em>action</em>
        </x-section-heading>
        <div class="mt-10 flex flex-wrap items-center gap-3">
            <x-button :href="route('booking.create')" icon="arrow-right">Book Now</x-button>
            <x-button variant="dark">Check availability</x-button>
            <x-button variant="outline">See yachts</x-button>
            <x-button size="sm" variant="dark">Small</x-button>
            <x-button size="lg">Large</x-button>
            <x-button size="xl" variant="dark" icon="send">Extra large</x-button>
            <x-button disabled>Disabled</x-button>
        </div>
        <div class="mt-10 flex flex-wrap items-center gap-3">
            <x-badge>Navy</x-badge>
            <x-badge variant="brass" icon="sparkles">Brass</x-badge>
            <x-badge variant="success">Approved</x-badge>
            <x-badge variant="warning">Pending review</x-badge>
            <x-badge variant="danger">Rejected</x-badge>
            <span class="rounded-2xl bg-navy-950 p-3"><x-badge variant="unavailable" icon="calendar-x">Unavailable</x-badge></span>
            <x-stars :rating="4.8" />
        </div>
    </section>

    {{-- Dark section --}}
    <section class="bg-navy-950 py-20 text-white">
        <div class="container">
            <x-section-heading eyebrow="Experiences & Events" center dark subtitle="The same component on a navy background.">
                Chicago's best moments are <em>on the water</em>
            </x-section-heading>
            <div class="mt-10 flex flex-wrap justify-center gap-3">
                <x-button icon="arrow-right">Primary</x-button>
                <x-button variant="light">Light outline</x-button>
                <x-button variant="white">White</x-button>
            </div>
            <div class="mt-12 grid grid-cols-2 gap-6 text-center md:grid-cols-4">
                <div><p class="font-display text-4xl"><span data-count="25" data-suffix="K+">25K+</span></p><p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-white/50">Guests hosted</p></div>
                <div><p class="font-display text-4xl"><span data-count="4800" data-suffix="+">4,800+</span></p><p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-white/50">Charters</p></div>
                <div><p class="font-display text-4xl"><span data-count="20" data-suffix="+">20+</span></p><p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-white/50">Licensed captains</p></div>
                <div><p class="font-display text-4xl"><span data-count="4">4</span></p><p class="mt-1 text-xs font-bold uppercase tracking-[0.18em] text-white/50">Chicago harbors</p></div>
            </div>
        </div>
    </section>

    {{-- Yacht cards --}}
    <section class="container py-20">
        <x-section-heading eyebrow="Our Fleet" subtitle="The third card shows the Unavailable state: still visible, details open, booking blocked.">
            Find the right yacht <em>for your crew</em>
        </x-section-heading>
        <div class="mt-12 grid gap-8 md:grid-cols-2 lg:grid-cols-3">
            @foreach ($cards as $i => $yacht)
                <x-yacht-card :yacht="$yacht" :delay="$i + 1" />
            @endforeach
        </div>
    </section>

    {{-- Forms --}}
    <section class="bg-white py-20">
        <div class="container grid gap-12 lg:grid-cols-2">
            <div>
                <x-section-heading eyebrow="Forms" subtitle="Labels, required marks, hints, icons and the error state (aria-invalid + message).">
                    Form <em>fields</em>
                </x-section-heading>
            </div>
            <form class="grid gap-5 sm:grid-cols-2" novalidate>
                <x-form.input name="name" label="Full name" required autocomplete="name" placeholder="Jane Smith" />
                <x-form.input name="email" type="email" label="Email" required icon="mail" placeholder="jane@email.com" error="Please enter a valid email address." />
                <x-form.input name="phone" type="tel" label="Phone" autocomplete="tel" placeholder="(312) 000-0000" hint="We only call about your booking." />
                <x-form.select name="guests" label="Group size" :options="['2-6' => '2–6 guests', '7-13' => '7–13 guests', '14-49' => '14–49 guests']" placeholder="Choose…" />
                <x-form.textarea name="message" label="Message" required rows="4" class="sm:col-span-2" placeholder="Tell us about your occasion…" />
                <x-form.checkbox name="agree" class="sm:col-span-2">I agree to the <a href="{{ route('legal.terms') }}" class="font-semibold underline">terms</a> and <a href="{{ route('legal.cancellation') }}" class="font-semibold underline">cancellation policy</a>.</x-form.checkbox>
                <div class="sm:col-span-2"><x-button type="button" variant="dark" icon="send">Send message</x-button></div>
            </form>
        </div>
    </section>

    {{-- Accordion + lightbox --}}
    <section class="container grid gap-16 py-20 lg:grid-cols-2">
        <div>
            <x-section-heading eyebrow="FAQ">Good <em>questions</em></x-section-heading>
            <x-accordion class="mt-8">
                <x-accordion.item question="Is the captain included in the price?" :open="true">
                    Yes. Every package includes a licensed USCG captain (and crew on larger yachts). The captain fee is shown as its own line in your quote.
                </x-accordion.item>
                <x-accordion.item question="Can we bring our own food and drinks?">
                    Absolutely — most charters are BYOB (21+) and you're welcome to bring food. No glass bottles please.
                </x-accordion.item>
                <x-accordion.item question="What happens if the weather is bad?">
                    Safety comes first. If your captain cancels due to weather, you can reschedule for free or receive a full refund.
                </x-accordion.item>
            </x-accordion>
        </div>
        <div>
            <x-section-heading eyebrow="Gallery">Tap a <em>photo</em></x-section-heading>
            <div class="mt-8 grid grid-cols-3 gap-3" data-lightbox-gallery>
                @foreach (['yacht-azure', 'deck-lounge', 'interior-salon', 'exp-fireworks', 'exp-swim', 'chicago-river-night'] as $photo)
                    <button type="button" data-lightbox="{{ asset('images/'.$photo.'.jpg') }}" class="group overflow-hidden rounded-2xl focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass-400" aria-label="Open photo {{ $loop->iteration }}">
                        <img src="{{ asset('images/'.$photo.'.jpg') }}" alt="" loading="lazy" class="aspect-square w-full object-cover transition duration-700 group-hover:scale-110">
                    </button>
                @endforeach
            </div>
        </div>
    </section>
@endsection
