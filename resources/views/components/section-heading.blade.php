{{--
    Section eyebrow + title (+ optional subtitle), as used across the mockup.

    <x-section-heading eyebrow="Our Fleet" subtitle="From intimate sailboats…">
        Find the right yacht <em>for your crew</em>
    </x-section-heading>

    An <em> inside the title gets the brass accent colour automatically.
    Props: eyebrow, subtitle, center (bool), dark (bool, for navy sections), as (h1|h2|h3),
           tight (bool, default true: `leading-tight` like most mockup headings; false for the few without it)

    Line height matches the mockup's Tailwind v3 rendering: tight on mobile, 1.0 from md (where v3's
    md:text-5xl overrode leading-tight).
--}}
@props(['eyebrow' => null, 'subtitle' => null, 'center' => false, 'dark' => false, 'as' => 'h2', 'tight' => true])

<div {{ $attributes->class(['text-center' => $center]) }}>
    @if ($eyebrow)
        <p @class([
            'flex items-center gap-3 text-xs font-bold uppercase tracking-[0.3em]',
            'justify-center' => $center,
            'text-brass-300' => $dark,
            'text-brass-600' => ! $dark,
        ])>
            <span class="h-px w-10 bg-brass-400" aria-hidden="true"></span>{{ $eyebrow }}@if ($center)<span class="h-px w-10 bg-brass-400" aria-hidden="true"></span>@endif
        </p>
    @endif

    <{{ $as }} @class([
        'font-display text-4xl md:text-5xl',
        'leading-tight md:leading-none' => $tight,
        'mt-4' => $eyebrow,
        'text-white [&_em]:text-brass-300' => $dark,
        'text-navy-950 [&_em]:text-brass-600' => ! $dark,
    ])>{{ $slot }}</{{ $as }}>

    @if ($subtitle)
        <p @class(['mt-4', 'mx-auto max-w-2xl' => $center, 'text-white/65' => $dark, 'text-navy-500' => ! $dark])>{{ $subtitle }}</p>
    @endif
</div>
