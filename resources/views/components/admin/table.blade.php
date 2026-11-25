{{--
    Data table: scrolls sideways on small screens (the wrapper is `relative`, see CLAUDE.md).
    <x-admin.table><x-slot:head><th scope="col">Ref</th>…</x-slot:head> <tr><td>…</td></tr> </x-admin.table>
    `min` sets the width below which it scrolls (default min-w-[44rem]).
--}}
@props(['min' => 'min-w-[44rem]'])

<div class="no-scrollbar relative overflow-x-auto">
    <table {{ $attributes->class($min.' w-full text-left text-sm [&_td]:border-t [&_td]:border-navy-100 [&_td]:px-4 [&_td]:py-3.5 [&_td]:align-middle [&_th]:whitespace-nowrap [&_th]:bg-navy-50/70 [&_th]:px-4 [&_th]:py-3 [&_th]:text-[11px] [&_th]:font-bold [&_th]:uppercase [&_th]:tracking-wider [&_th]:text-navy-400 [&_tbody_tr:hover]:bg-navy-50/40') }}>
        <thead><tr>{{ $head }}</tr></thead>
        <tbody>{{ $slot }}</tbody>
    </table>
</div>
