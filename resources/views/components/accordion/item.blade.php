{{-- One accordion question. <x-accordion.item question="Is the captain included?" :open="true">Answer…</x-accordion.item> --}}
@props(['question', 'open' => false])

@php($id = 'acc-'.substr(md5($question), 0, 8))

<div @class(['acc-item', 'is-open' => $open])>
    <h3>
        <button type="button" id="{{ $id }}-btn" class="acc-trigger flex w-full items-center justify-between gap-6 py-6 text-left text-lg font-bold text-navy-950 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-brass-400"
                aria-expanded="{{ $open ? 'true' : 'false' }}" aria-controls="{{ $id }}-panel">
            {{ $question }}<i data-lucide="plus" class="acc-icon h-5 w-5 shrink-0 text-brass-500"></i>
        </button>
    </h3>
    <div id="{{ $id }}-panel" class="acc-panel" role="region" aria-labelledby="{{ $id }}-btn" @unless ($open) inert @endunless>
        <div><div class="pb-6 leading-relaxed text-navy-500">{{ $slot }}</div></div>
    </div>
</div>
