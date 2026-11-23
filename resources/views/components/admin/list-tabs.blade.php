{{-- Active / Trash tabs above an admin list. <x-admin.list-tabs :url="route('admin.harbors.index')" :in-trash="$inTrash" :counts="$counts" /> --}}
@props(['url', 'inTrash' => false, 'counts' => ['active' => 0, 'trash' => 0], 'activeLabel' => 'Active'])

<nav {{ $attributes->class('inline-flex shrink-0 gap-1 self-start whitespace-nowrap rounded-full bg-white p-1 text-xs font-bold shadow-xs ring-1 ring-navy-100') }} aria-label="Show">
    <a href="{{ $url }}" @class(['rounded-full px-4 py-2', 'bg-navy-950 text-white' => ! $inTrash, 'text-navy-500 hover:text-navy-950' => $inTrash]) @if (! $inTrash) aria-current="page" @endif>{{ $activeLabel }} <span @class(['ml-1', 'text-white/60' => ! $inTrash, 'text-navy-300' => $inTrash])>{{ $counts['active'] }}</span></a>
    <a href="{{ $url }}?view=trash" @class(['inline-flex items-center gap-1.5 rounded-full px-4 py-2', 'bg-navy-950 text-white' => $inTrash, 'text-navy-500 hover:text-navy-950' => ! $inTrash]) @if ($inTrash) aria-current="page" @endif><i data-lucide="trash-2" class="h-3.5 w-3.5"></i>Trash <span @class(['text-white/60' => $inTrash, 'text-navy-300' => ! $inTrash])>{{ $counts['trash'] }}</span></a>
</nav>
