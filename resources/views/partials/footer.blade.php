{{--
    Site footer (mockup layout.js). $footerHarbors and $footerExperiences come from
    App\View\Composers\FooterComposer. $mobileBar adds room for fixed mobile bottom bars.
--}}
@php
    $business = config('site.business');
    $social = config('site.social');
@endphp

<footer id="site-footer" @class(['relative overflow-hidden bg-navy-950 text-white', 'pb-24 lg:pb-0' => $mobileBar])>
    <div class="pointer-events-none absolute -right-40 -top-40 h-96 w-96 rounded-full bg-brass-400/10 blur-3xl" aria-hidden="true"></div>

    <div class="container relative">
        {{-- Harbors --}}
        <div class="grid gap-6 border-b border-white/10 py-14 md:grid-cols-2 lg:grid-cols-4">
            @foreach ($footerHarbors as $harbor)
                <a href="https://www.google.com/maps/search/?api=1&amp;query={{ urlencode($harbor->map_query) }}" target="_blank" rel="noopener noreferrer"
                   class="group rounded-2xl border border-white/10 p-5 transition hover:border-brass-400/60 hover:bg-white/5">
                    <div class="flex items-center justify-between">
                        <i data-lucide="map-pin" class="h-5 w-5 text-brass-300"></i>
                        <i data-lucide="arrow-up-right" class="h-4 w-4 text-white/30 transition group-hover:text-brass-300"></i>
                    </div>
                    <p class="mt-4 font-display text-lg">{{ $harbor->name }}</p>
                    <p class="text-sm text-white/55">{{ $harbor->area }}<span class="sr-only"> (opens Google Maps)</span></p>
                </a>
            @endforeach
        </div>

        <div class="grid gap-12 py-16 lg:grid-cols-12">
            {{-- Brand + newsletter --}}
            <div class="lg:col-span-4">
                <x-logo size="lg" />
                <p class="mt-6 max-w-sm text-sm leading-relaxed text-white/60">{{ $business['tagline'] }}</p>
                {{-- Phase 1: demo only (resources/js/ui/newsletter.js). Phase 9 posts to the newsletter endpoint. --}}
                <form data-newsletter class="mt-8 flex max-w-sm overflow-hidden rounded-full bg-white/10 p-1.5 ring-1 ring-white/10 focus-within:ring-brass-400" novalidate>
                    <label for="nl-email" class="sr-only">Email address</label>
                    <input id="nl-email" name="email" type="email" required maxlength="254" autocomplete="email" placeholder="Your email for deals"
                           class="min-w-0 flex-1 bg-transparent px-4 text-sm text-white placeholder:text-white/40 focus:outline-hidden">
                    <button type="submit" class="rounded-full bg-brass-400 px-5 py-2.5 text-sm font-bold text-navy-950 hover:bg-brass-300">Subscribe</button>
                </form>
            </div>

            <div class="grid grid-cols-2 gap-10 sm:grid-cols-3 lg:col-span-8">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.25em] text-brass-300">Explore</p>
                    <ul class="mt-5 space-y-3 text-sm text-white/65">
                        @foreach (config('site.nav') as $item)
                            <li><a href="{{ route($item['route']) }}" class="hover:text-white">{{ $item['label'] }}</a></li>
                        @endforeach
                        <li><a href="{{ route('yachts.index') }}" class="hover:text-white">Book Now</a></li>
                        <li><a href="{{ route('owner.dashboard') }}" class="hover:text-white">Owner Dashboard</a></li>
                    </ul>
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.25em] text-brass-300">Experiences</p>
                    <ul class="mt-5 space-y-3 text-sm text-white/65">
                        @foreach ($footerExperiences as $experience)
                            <li><a href="{{ route('yachts.index', ['experience' => $experience->slug]) }}" class="hover:text-white">{{ $experience->name }}</a></li>
                        @endforeach
                    </ul>
                </div>
                <div class="col-span-2 sm:col-span-1">
                    <p class="text-xs font-bold uppercase tracking-[0.25em] text-brass-300">Contact</p>
                    <ul class="mt-5 space-y-4 text-sm text-white/65">
                        <li><a href="tel:{{ $business['phone_href'] }}" class="flex items-center gap-3 hover:text-white"><i data-lucide="phone" class="h-4 w-4 text-brass-300"></i>{{ $business['phone'] }}</a></li>
                        {{-- In narrow columns the address wraps after the "@" instead of mid-word (escaped first, then <wbr> added). --}}
                        <li><a href="mailto:{{ $business['email'] }}" class="flex items-center gap-3 hover:text-white"><i data-lucide="mail" class="h-4 w-4 shrink-0 text-brass-300"></i><span class="min-w-0">{!! str_replace('@', '@<wbr>', e($business['email'])) !!}</span></a></li>
                        <li class="flex items-center gap-3"><i data-lucide="clock" class="h-4 w-4 shrink-0 text-brass-300"></i>{{ $business['hours'] }}</li>
                        <li class="flex items-center gap-3"><i data-lucide="map-pin" class="h-4 w-4 shrink-0 text-brass-300"></i>{{ $business['office'] }}</li>
                    </ul>
                    <div class="mt-6 flex gap-2">
                        @foreach (['instagram' => 'Instagram', 'facebook' => 'Facebook', 'youtube' => 'YouTube'] as $network => $networkLabel)
                            <a href="{{ $social[$network] }}" @if ($social[$network] !== '#') target="_blank" rel="noopener noreferrer" @endif
                               class="grid h-10 w-10 place-items-center rounded-full bg-white/10 transition hover:bg-brass-400 hover:text-navy-950" aria-label="{{ $networkLabel }}">
                                <i data-lucide="{{ $network }}" class="h-4 w-4"></i>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-4 border-t border-white/10 py-8 text-xs text-white/45 md:flex-row md:items-center md:justify-between">
            <p>© {{ now(config('app.display_timezone'))->year }} {{ $business['name'] }}. All rights reserved.</p>
            <div class="flex flex-wrap gap-x-6 gap-y-2">
                <a href="{{ route('legal.cancellation') }}" class="hover:text-white">Cancellation Policy</a>
                <a href="{{ route('pricing') }}#policies" class="hover:text-white">Weather Policy</a>
                <a href="{{ route('legal.privacy') }}" class="hover:text-white">Privacy</a>
                <a href="{{ route('legal.terms') }}" class="hover:text-white">Terms</a>
            </div>
        </div>
    </div>
</footer>
