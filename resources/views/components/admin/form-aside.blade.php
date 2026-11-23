{{--
    The right-hand column of an admin record form (3.7). On large screens it follows the page while the form
    scrolls: it sticks under the top bar (and under the boat form's section chips with `below-nav`), so the
    checklist, the "On the site" switches and the Save button stay in view. A column taller than the screen
    scrolls on its own, and its Save / Cancel buttons (x-admin.form-actions) stick to its bottom edge.
    Below lg it is an ordinary block under the main column.

    <x-admin.form-aside below-nav> …cards… <x-admin.form-actions … /> … </x-admin.form-aside>
--}}
@props(['belowNav' => false])

<div {{ $attributes->class([
    'min-w-0 space-y-6 lg:sticky lg:-mx-1 lg:-mt-1 lg:self-start lg:overflow-y-auto lg:px-1 lg:pt-1 lg:pb-1 lg:[scrollbar-width:thin]',
    'lg:top-34 lg:max-h-[calc(100dvh-9.5rem)]' => $belowNav,
    'lg:top-20 lg:max-h-[calc(100dvh-6rem)]' => ! $belowNav,
]) }} data-form-aside>
    {{ $slot }}
</div>
