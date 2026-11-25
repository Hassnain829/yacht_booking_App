{{--
    Button / link button (mockup CYB.cls.btnPrimary / btnDark / btnOutline).

    <x-button :href="route('yachts.index')" icon="arrow-right">Book Now</x-button>
    <x-button variant="dark" type="submit" size="lg">Send message</x-button>

    variant: primary | dark | outline | light (outline on dark backgrounds) | white
    size:    sm | md | lg | xl | block (full width, tall)
    Change the size with `size`, not by adding px-/py- classes (they would conflict).
--}}
@props([
    'href' => null,
    'variant' => 'primary',
    'size' => 'md',
    'type' => 'button',
    'icon' => null,
    'iconLeft' => null,
])

@php
    $variants = [
        'primary' => 'bg-brass-400 text-navy-950 shadow-glow hover:-translate-y-0.5 hover:bg-brass-300',
        'dark' => 'bg-navy-950 text-white hover:-translate-y-0.5 hover:bg-navy-800',
        'outline' => 'border border-navy-200 text-navy-900 hover:border-navy-950 hover:bg-navy-950 hover:text-white',
        'light' => 'border border-white/30 text-white hover:border-white hover:bg-white hover:text-navy-950',
        'white' => 'bg-white text-navy-950 hover:-translate-y-0.5 hover:bg-brass-300',
    ];
    $sizes = [
        'sm' => 'px-5 py-2.5',
        'md' => 'px-6 py-3',
        'lg' => 'px-7 py-3.5',
        'xl' => 'px-8 py-4',
        'block' => 'w-full px-6 py-4',
    ];
    // If the caller sets the display (e.g. class="hidden sm:inline-flex"), don't add our own
    // inline-flex: two display classes on one element conflict, and Tailwind v4 would pick inline-flex.
    $callerSetsDisplay = (bool) preg_match('/(^|\s)(hidden|block|flex|inline-flex|grid)(\s|$)/', (string) $attributes->get('class', ''));
    $classes = ($callerSetsDisplay ? '' : 'inline-flex ')
        .'items-center justify-center gap-2 rounded-full text-sm font-bold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brass-400 disabled:pointer-events-none disabled:opacity-50 '
        .($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($classes) }}>
        @if ($iconLeft)<i data-lucide="{{ $iconLeft }}" class="h-4 w-4"></i>@endif
        {{ $slot }}
        @if ($icon)<i data-lucide="{{ $icon }}" class="h-4 w-4"></i>@endif
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class($classes) }}>
        @if ($iconLeft)<i data-lucide="{{ $iconLeft }}" class="h-4 w-4"></i>@endif
        {{ $slot }}
        @if ($icon)<i data-lucide="{{ $icon }}" class="h-4 w-4"></i>@endif
    </button>
@endif
