{{-- 5-star rating row (mockup CYB.ui.stars). --}}
@props(['rating' => 5, 'size' => 'h-4 w-4'])

<span {{ $attributes->class('inline-flex items-center gap-0.5') }} role="img" aria-label="Rated {{ number_format((float) $rating, 1) }} out of 5">
    @for ($i = 1; $i <= 5; $i++)
        <i data-lucide="star" @class([$size, 'fill-brass-400 text-brass-400' => $i <= round($rating), 'text-navy-200' => $i > round($rating)])></i>
    @endfor
</span>
