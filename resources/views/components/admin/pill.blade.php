{{-- Status pill. tone: success | warning | danger | navy | brass | muted --}}
@props(['tone' => 'navy'])

<span {{ $attributes->class(['inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2.5 py-0.5 text-[11px] font-bold', match ($tone) {
    'success' => 'bg-emerald-100 text-emerald-800',
    'warning' => 'bg-amber-100 text-amber-800',
    'danger' => 'bg-red-100 text-red-700',
    'brass' => 'bg-brass-100 text-brass-800',
    'muted' => 'bg-navy-50 text-navy-400',
    default => 'bg-navy-100 text-navy-700',
}]) }}>{{ $slot }}</span>
