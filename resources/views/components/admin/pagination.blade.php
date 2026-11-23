{{-- Footer of a paginated admin table: "Showing 1–20 of 54" + Previous / Next. --}}
@props(['paginator'])

@if ($paginator->hasPages())
    <div {{ $attributes->class('flex flex-wrap items-center justify-between gap-3 border-t border-navy-100 px-5 py-4 text-sm') }}>
        <p class="text-navy-500">Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}</p>
        <nav class="flex gap-2" aria-label="Pages">
            @if ($paginator->onFirstPage())<span class="rounded-full px-4 py-2 font-bold text-navy-300">Previous</span>@else<a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="rounded-full px-4 py-2 font-bold text-navy-700 ring-1 ring-navy-200 hover:bg-navy-50">Previous</a>@endif
            @if ($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}" rel="next" class="rounded-full px-4 py-2 font-bold text-navy-700 ring-1 ring-navy-200 hover:bg-navy-50">Next</a>@else<span class="rounded-full px-4 py-2 font-bold text-navy-300">Next</span>@endif
        </nav>
    </div>
@endif
