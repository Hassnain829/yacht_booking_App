{{-- Stat card. tone: default | dark | warning --}}
@props(['label', 'value', 'note' => null, 'icon' => null, 'tone' => 'default', 'href' => null])

@php
    $dark = $tone === 'dark';
    $tag = $href ? 'a' : 'div';
@endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->class([
    'block rounded-2xl p-5 ring-1 transition',
    'bg-navy-950 text-white ring-navy-950 shadow-card' => $dark,
    'bg-amber-50 ring-amber-200' => $tone === 'warning',
    'bg-white ring-navy-100 shadow-xs' => $tone === 'default',
    'hover:-translate-y-0.5 hover:shadow-card' => $href,
]) }}>
    <div class="flex items-center justify-between gap-3">
        <p @class(['text-xs font-bold uppercase tracking-[0.15em]', 'text-brass-300' => $dark, 'text-amber-800' => $tone === 'warning', 'text-navy-400' => $tone === 'default'])>{{ $label }}</p>
        @if ($icon)
            <i data-lucide="{{ $icon }}" @class(['h-5 w-5', 'text-brass-300' => $dark, 'text-amber-600' => $tone === 'warning', 'text-navy-300' => $tone === 'default'])></i>
        @endif
    </div>
    <p @class(['mt-2 text-2xl font-extrabold', 'text-navy-950' => ! $dark])>{{ $value }}</p>
    @if ($note)
        <p @class(['mt-1 text-xs', 'text-white/60' => $dark, 'text-navy-500' => ! $dark])>{{ $note }}</p>
    @endif
</{{ $tag }}>
