{{-- A card's brand as a small dark tile (saved cards: account, checkout). <x-card-brand :brand="$card->brandName()" /> --}}
@props(['brand'])

<span {{ $attributes->class('grid h-10 w-14 shrink-0 place-items-center rounded-lg bg-navy-950 text-[10px] font-extrabold uppercase tracking-wider text-white') }}>{{ ['Mastercard' => 'MC', 'Amex' => 'AMEX', 'Discover' => 'DISC'][$brand] ?? $brand }}</span>
