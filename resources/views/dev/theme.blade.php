{{-- Phase 0 QA page: theme, fonts, container, icons and JS helpers. Registered only when APP_ENV=local. --}}
<!DOCTYPE html>
<html lang="en" class="js scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>Theme check — {{ config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @fonts
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-shell font-sans text-navy-900 antialiased" data-page="dev-theme">
    <header class="bg-navy-950 text-white">
        <div class="container flex h-20 items-center justify-between">
            <a href="#" class="flex shrink-0 items-center gap-3 whitespace-nowrap">
                <img src="{{ asset('favicon.svg') }}" alt="" class="h-10 w-10">
                <span class="leading-none">
                    <span class="block font-display text-xl font-semibold">Chicago Yacht</span>
                    <span class="block text-[10px] font-bold uppercase tracking-[0.32em] text-brass-300">Booking</span>
                </span>
            </a>
            <span class="rounded-full bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.18em] text-brass-300">Phase 0 · theme check</span>
        </div>
    </header>

    <main class="container space-y-14 py-14">
        <section>
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-brass-600">Typography</p>
            <h1 class="mt-3 font-display text-5xl font-semibold leading-tight text-navy-950 md:text-6xl">Chicago from the water, <em class="font-medium text-brass-500">your way</em></h1>
            <div class="mt-6 grid gap-3 text-lg md:grid-cols-2">
                <p class="font-display font-medium">Playfair Display 500</p>
                <p class="font-display font-semibold">Playfair Display 600</p>
                <p class="font-display font-bold">Playfair Display 700</p>
                <p class="font-display font-semibold italic">Playfair Display 600 italic</p>
                <p class="font-normal">Manrope 400 — Private yacht charters on Lake Michigan.</p>
                <p class="font-medium">Manrope 500 — Licensed USCG captain included.</p>
                <p class="font-semibold">Manrope 600 — Sunset skyline cruises.</p>
                <p class="font-bold">Manrope 700 — Navy Pier fireworks.</p>
                <p class="font-extrabold">Manrope 800 — Book now</p>
            </div>
            <dl class="mt-6 grid gap-2 rounded-2xl bg-white p-5 text-sm shadow-card md:grid-cols-2" id="font-report">
                <div><dt class="font-bold text-navy-500">Body font in use</dt><dd data-font="body" class="font-mono">…</dd></div>
                <div><dt class="font-bold text-navy-500">Display font in use</dt><dd data-font="display" class="font-mono">…</dd></div>
            </dl>
        </section>

        <section>
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-brass-600">Colors</p>
            {{-- Full class names on purpose: Tailwind only generates classes it can find written out in full. --}}
            <div class="mt-4 grid grid-cols-11 gap-2" id="navy-swatches">
                @foreach (['bg-navy-50', 'bg-navy-100', 'bg-navy-200', 'bg-navy-300', 'bg-navy-400', 'bg-navy-500', 'bg-navy-600', 'bg-navy-700', 'bg-navy-800', 'bg-navy-900', 'bg-navy-950'] as $swatch)
                    <div class="h-14 rounded-xl ring-1 ring-black/5 {{ $swatch }}" title="{{ $swatch }}"></div>
                @endforeach
            </div>
            <div class="mt-2 grid grid-cols-11 gap-2">
                @foreach (['bg-brass-50', 'bg-brass-100', 'bg-brass-200', 'bg-brass-300', 'bg-brass-400', 'bg-brass-500', 'bg-brass-600', 'bg-brass-700', 'bg-brass-800', 'bg-brass-900'] as $swatch)
                    <div class="h-14 rounded-xl ring-1 ring-black/5 {{ $swatch }}" title="{{ $swatch }}"></div>
                @endforeach
                <div class="h-14 rounded-xl bg-shell ring-1 ring-navy-200" title="shell"></div>
            </div>
        </section>

        <section>
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-brass-600">Buttons, cards, badges (mockup classes)</p>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <a href="#" class="inline-flex items-center gap-2 whitespace-nowrap rounded-full bg-brass-400 px-6 py-3 text-sm font-bold text-navy-950 shadow-glow transition hover:bg-brass-300">Book Now <i data-lucide="arrow-right" class="h-4 w-4"></i></a>
                <a href="#" class="inline-flex items-center gap-2 rounded-full bg-navy-950 px-6 py-3 text-sm font-bold text-white transition hover:bg-navy-800">Check availability</a>
                <a href="#" class="inline-flex items-center gap-2 rounded-full px-6 py-3 text-sm font-bold text-navy-950 ring-1 ring-navy-200 transition hover:ring-navy-950">View fleet</a>
                <span class="rounded-full bg-brass-100 px-3 py-1 text-xs font-bold text-brass-800">Most Popular</span>
                <span class="rounded-full bg-navy-100 px-3 py-1 text-xs font-bold text-navy-700">Unavailable</span>
                <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">Approved</span>
                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">Pending review</span>
                <span class="spinner text-brass-500" aria-label="Loading"></span>
            </div>
            <div class="mt-6 grid gap-6 md:grid-cols-3">
                <article class="overflow-hidden rounded-3xl bg-white shadow-card">
                    <img src="{{ asset('images/yacht-azure.jpg') }}" alt="The Azure" class="aspect-[4/3] w-full object-cover">
                    <div class="p-6">
                        <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-navy-400">Motor Yacht · 62 ft</p>
                        <h3 class="mt-1 font-display text-2xl font-semibold text-navy-950">The Azure</h3>
                        <p class="mt-3 text-sm text-navy-600">From <span class="font-extrabold text-navy-950" data-money="45000"></span>/hr</p>
                    </div>
                </article>
                <div class="rounded-3xl bg-white p-6 shadow-soft">
                    <p class="font-bold">shadow-soft</p>
                    <input class="mt-4 w-full rounded-xl border border-navy-200 bg-white px-4 py-3.5 text-sm focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100" placeholder="Placeholder text (gray-400)">
                    <div class="mt-4 border-t pt-4 text-sm text-navy-500">Default border (gray-200, v3 style)</div>
                </div>
                <div class="relative overflow-hidden rounded-3xl">
                    <img src="{{ asset('images/hero-poster.jpg') }}" alt="" class="h-full w-full object-cover">
                    <div class="absolute inset-x-4 bottom-4 rounded-2xl bg-white/15 p-4 text-white ring-1 ring-white/25 backdrop-blur">
                        <p class="font-display text-xl font-semibold">Glass panel</p>
                        <p class="text-sm text-white/80">backdrop-blur + bg-white/15</p>
                    </div>
                </div>
            </div>
        </section>

        <section>
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-brass-600">Container</p>
            <div class="mt-4 rounded-2xl bg-navy-950 px-5 py-4 font-mono text-sm text-brass-300" id="container-report">…</div>
        </section>

        <section>
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-brass-600">JS helpers</p>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <dl class="space-y-2 rounded-2xl bg-white p-5 text-sm shadow-card" id="format-report"></dl>
                <div class="flex flex-wrap content-start gap-3 rounded-2xl bg-white p-5 shadow-card">
                    <button type="button" data-toast="success" class="rounded-full bg-navy-950 px-5 py-2.5 text-sm font-bold text-white">Show success toast</button>
                    <button type="button" data-toast="error" class="rounded-full bg-red-600 px-5 py-2.5 text-sm font-bold text-white">Show error toast</button>
                    <button type="button" data-toast="xss" class="rounded-full px-5 py-2.5 text-sm font-bold ring-1 ring-navy-200">Toast with &lt;script&gt; text</button>
                </div>
            </div>
        </section>

        <section>
            <p class="text-xs font-bold uppercase tracking-[0.25em] text-brass-600">Icons (<span id="icon-count">0</span> registered)</p>
            <div class="mt-4 grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-10" id="icon-grid"></div>
        </section>
    </main>
</body>
</html>
