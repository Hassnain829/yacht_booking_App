{{--
    Small status / label pill.
    variant: navy | brass | success | warning | danger | light (on photos) | dark (on photos) | unavailable
--}}
@props(['variant' => 'navy', 'icon' => null])

@php
    $variants = [
        'navy' => 'bg-navy-100 text-navy-700',
        'brass' => 'bg-brass-100 text-brass-800',
        'success' => 'bg-emerald-100 text-emerald-800',
        'warning' => 'bg-amber-100 text-amber-800',
        'danger' => 'bg-red-100 text-red-700',
        'light' => 'bg-white/95 text-navy-900 uppercase tracking-wider text-[11px]',
        'dark' => 'bg-navy-950/70 text-white backdrop-blur',
        'unavailable' => 'bg-navy-950 text-white uppercase tracking-wider text-[11px] ring-1 ring-white/20',
    ];
@endphp

<span {{ $attributes->class(['inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold', $variants[$variant] ?? $variants['navy']]) }}>
    @if ($icon)<i data-lucide="{{ $icon }}" class="h-3.5 w-3.5"></i>@endif
    {{ $slot }}
</span>
