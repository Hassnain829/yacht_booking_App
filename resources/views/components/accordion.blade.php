{{--
    FAQ accordion wrapper (mockup data-accordion). Put <x-accordion.item> children inside.
    multi: allow several items open at once (default: one at a time).
--}}
@props(['multi' => false])

<div {{ $attributes->class('divide-y divide-navy-100 border-y border-navy-100') }} data-accordion="{{ $multi ? 'multi' : 'single' }}">
    {{ $slot }}
</div>
