@extends('layouts.app', [
    'title' => 'Contact Us',
    'description' => 'Questions, custom events or group quotes — call, email or message the Chicago Yacht Booking crew. We reply within the hour, daily 8 AM – 9 PM.',
    'page' => 'contact',
])

@php
    $business = config('site.business');
    $guestOptions = ['2-6' => '2–6 guests', '7-13' => '7–13 guests', '14-49' => '14–49 guests', '50-80' => '50–80 guests', '80+' => '80+ (multi-yacht)'];
@endphp

@section('content')
    <x-page-hero image="images/chicago-architecture.jpg" crumb="Contact Us" class="pb-36!" image-class="opacity-45" overlay-class="from-navy-950/80 via-navy-950/50 to-navy-950"
                 intro="Questions, custom events or group quotes — our crew replies within the hour, every day from 8 AM to 9 PM.">
        Let's plan your <em>day on the water</em>
    </x-page-hero>

    <section class="relative z-10 -mt-24 pb-24">
        <div class="container">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <a href="tel:{{ $business['phone_href'] }}" class="reveal group rounded-3xl bg-white p-6 shadow-card ring-1 ring-navy-100 transition hover:-translate-y-1"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-navy-950 text-brass-300 transition group-hover:bg-brass-400 group-hover:text-navy-950"><i data-lucide="phone" class="h-5 w-5"></i></span><p class="mt-5 text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Call us</p><p class="mt-1 text-lg font-bold text-navy-950">{{ $business['phone'] }}</p></a>
                <a href="mailto:{{ $business['email'] }}" class="reveal group rounded-3xl bg-white p-6 shadow-card ring-1 ring-navy-100 transition hover:-translate-y-1" data-delay="1"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-navy-950 text-brass-300 transition group-hover:bg-brass-400 group-hover:text-navy-950"><i data-lucide="mail" class="h-5 w-5"></i></span><p class="mt-5 text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Email</p><p class="mt-1 text-[15px] font-bold text-navy-950">{!! str_replace('@', '@<wbr>', e($business['email'])) !!}</p></a>
                <div class="reveal rounded-3xl bg-white p-6 shadow-card ring-1 ring-navy-100" data-delay="2"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-navy-950 text-brass-300"><i data-lucide="clock" class="h-5 w-5"></i></span><p class="mt-5 text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Hours</p><p class="mt-1 text-lg font-bold text-navy-950">Daily 8 AM – 9 PM</p></div>
                <div class="reveal rounded-3xl bg-white p-6 shadow-card ring-1 ring-navy-100" data-delay="3"><span class="grid h-12 w-12 place-items-center rounded-2xl bg-navy-950 text-brass-300"><i data-lucide="map-pin" class="h-5 w-5"></i></span><p class="mt-5 text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Main office</p><p class="mt-1 text-lg font-bold text-navy-950">DuSable Harbor, Chicago</p></div>
            </div>

            <div class="mt-12 grid gap-10 lg:grid-cols-[1.3fr_1fr]">
                <div class="reveal rounded-[2rem] bg-white p-7 shadow-card ring-1 ring-navy-100 md:p-10">
                    <div id="contact-success" class="hidden py-16 text-center" tabindex="-1">
                        <span class="mx-auto grid h-20 w-20 place-items-center rounded-full bg-emerald-50 text-emerald-600"><i data-lucide="circle-check" class="h-10 w-10"></i></span>
                        <h2 class="mt-6 font-display text-4xl text-navy-950">Message received!</h2>
                        <p class="mx-auto mt-3 max-w-md text-navy-500">Thanks <span id="cs-name"></span> — a charter expert will reply within the hour. Want to lock in a date right now?</p>
                        <div class="mt-8 flex justify-center gap-3">
                            <a href="{{ route('yachts.index') }}" class="inline-flex items-center gap-2 rounded-full bg-brass-400 px-7 py-3.5 text-sm font-bold text-navy-950 hover:bg-brass-300">Book online</a>
                            <button type="button" id="cs-again" class="rounded-full border border-navy-200 px-7 py-3.5 text-sm font-bold hover:border-navy-950">Send another</button>
                        </div>
                    </div>

                    {{-- Phase 1: UI + client-side checks only (resources/js/pages/contact.js).
                         Phase 9: POST /contact → ContactRequest (validation, honeypot, Turnstile, throttle) → store + emails. --}}
                    <form id="contact-form" action="{{ route('contact') }}" method="post" novalidate>
                        @csrf
                        <h2 class="font-display text-3xl text-navy-950">Send us a message</h2>
                        <p class="mt-2 text-sm text-navy-500">Fields marked * are required.</p>
                        <div class="mt-8 grid gap-5 sm:grid-cols-2">
                            <x-form.input name="name" label="Full name" required maxlength="100" autocomplete="name" placeholder="Jane Smith" />
                            <x-form.input name="email" type="email" label="Email" required maxlength="254" autocomplete="email" placeholder="jane@email.com" />
                            <x-form.input name="phone" type="tel" label="Phone" maxlength="30" autocomplete="tel" placeholder="(312) 000-0000" />
                            <x-form.input name="date" type="date" label="Preferred date" :min="$today" />
                            <x-form.select name="guests" label="Group size" :options="$guestOptions" />
                            <x-form.select name="experience" label="Interested in" :options="$experiences->pluck('name', 'slug')->all()" placeholder="Not sure yet" />
                            <x-form.textarea name="message" label="Message" required maxlength="2000" rows="5" class="sm:col-span-2" placeholder="Tell us about your occasion, ideal timing and anything special…" />
                        </div>
                        {{-- Honeypot: humans never see or fill this field. --}}
                        <div class="hidden" aria-hidden="true"><label for="f-website">Website</label><input id="f-website" type="text" name="website" tabindex="-1" autocomplete="off"></div>
                        <x-button type="submit" variant="dark" size="xl" icon="send" class="mt-8 w-full sm:w-auto"><span class="btn-label">Send message</span></x-button>
                    </form>
                </div>

                <div class="space-y-6">
                    @if ($harbor)
                        <div class="reveal overflow-hidden rounded-[2rem] ring-1 ring-navy-100" data-delay="1">
                            <iframe title="Map of {{ $harbor->name }}, Chicago" src="https://www.google.com/maps?q={{ urlencode($harbor->map_query) }}&amp;output=embed" class="h-80 w-full border-0" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                        </div>
                    @endif
                    <div class="reveal rounded-[2rem] bg-navy-950 p-8 text-white" data-delay="2">
                        <p class="font-display text-2xl">Quick answers</p>
                        <div class="mt-4 divide-y divide-white/10" data-accordion="single">
                            @foreach ($quickAnswers as $qa)
                                @php($id = 'qa-'.$loop->index)
                                <div class="acc-item">
                                    <button type="button" id="{{ $id }}-btn" class="acc-trigger flex w-full items-center justify-between gap-4 py-4 text-left text-sm font-bold" aria-expanded="false" aria-controls="{{ $id }}-panel">{{ $qa->question }}<i data-lucide="plus" class="acc-icon h-4 w-4 shrink-0 text-brass-300"></i></button>
                                    <div id="{{ $id }}-panel" class="acc-panel" role="region" aria-labelledby="{{ $id }}-btn" inert><div><p class="pb-4 text-sm text-white/60">{{ $qa->answer }}</p></div></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
