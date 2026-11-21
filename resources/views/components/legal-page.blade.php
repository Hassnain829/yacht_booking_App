{{--
    Legal / long-form page: photo hero, "On this page" menu, text in .legal styles.
    <x-legal-page crumb="Privacy Policy" image="…" updated="2026-11-21" :toc="['collect' => 'What we collect', …]">
        <x-slot:heading>Privacy <em>Policy</em></x-slot:heading>
        <h2 id="collect">What we collect</h2> …
    </x-legal-page>
    DRAFT TEXT: must be reviewed by the client (and a lawyer) before launch. Phase 10 moves it into the CMS.
--}}
@props(['crumb', 'image', 'updated', 'intro' => null, 'toc' => []])

<x-page-hero :image="$image" :crumb="$crumb" :intro="$intro">{{ $heading }}</x-page-hero>

<section class="bg-white py-16 md:py-24">
    <div class="container grid max-w-5xl gap-12 lg:grid-cols-[14rem_minmax(0,1fr)]">
        <nav class="hidden lg:block" aria-label="On this page">
            <div class="sticky top-28">
                <p class="text-xs font-bold uppercase tracking-[0.2em] text-navy-400">On this page</p>
                <ul class="mt-4 space-y-2 border-l border-navy-100 text-sm">
                    @foreach ($toc as $anchor => $label)
                        <li><a href="#{{ $anchor }}" class="-ml-px block border-l-2 border-transparent pl-4 text-navy-500 transition hover:border-brass-400 hover:text-navy-950">{{ $label }}</a></li>
                    @endforeach
                </ul>
            </div>
        </nav>
        <article class="legal min-w-0 max-w-3xl">
            @if (app()->isLocal())
                <p class="mb-8 flex items-start gap-2 rounded-2xl bg-amber-50 p-4 text-sm font-semibold text-amber-900 ring-1 ring-amber-200"><i data-lucide="triangle-alert" class="h-5 w-5 shrink-0"></i>Draft text for review — to be checked by the client and a lawyer before launch. (Only shown locally.)</p>
            @endif
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-navy-400">Last updated {{ \Carbon\CarbonImmutable::parse($updated)->format('F j, Y') }}</p>
            {{ $slot }}
        </article>
    </div>
</section>
