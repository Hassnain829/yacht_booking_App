{{-- Sortable column header. The controller whitelists `sort` and `dir`; anything else falls back to the default. --}}
@props(['column', 'label', 'sort', 'dir'])

@php
    $isActive = $sort === $column;
    $next = $isActive && $dir === 'asc' ? 'desc' : 'asc';
@endphp
<th scope="col" @if ($isActive) aria-sort="{{ $dir === 'asc' ? 'ascending' : 'descending' }}" @endif>
    <a href="{{ request()->fullUrlWithQuery(['sort' => $column, 'dir' => $next, 'page' => null]) }}" @class(['inline-flex items-center gap-1 hover:text-navy-950', 'text-navy-950' => $isActive])>
        {{ $label }}<i data-lucide="{{ $isActive ? ($dir === 'asc' ? 'arrow-up' : 'arrow-down') : 'arrow-up-down' }}" class="h-3 w-3"></i>
    </a>
</th>
