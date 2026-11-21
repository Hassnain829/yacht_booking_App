{{-- Admin card. `flush` removes the body padding (for tables). Use min-w-0 on grid items that hold tables. --}}
@props(['title' => null, 'flush' => false])

<section {{ $attributes->class('min-w-0 rounded-2xl bg-white shadow-xs ring-1 ring-navy-100') }}>
    @if ($title || isset($actions))
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-navy-100 px-5 py-4">
            <h2 class="text-base font-extrabold text-navy-950">{{ $title }}</h2>
            {{ $actions ?? '' }}
        </div>
    @endif
    <div @class(['p-5' => ! $flush])>{{ $slot }}</div>
</section>
