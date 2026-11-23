{{--
    Phones and tablets (below lg): the Save / Cancel bar of an admin record form. Put it last inside the <form>.
    It sticks to the bottom of the screen while the form is in view, so saving never needs a scroll, and lands at
    the end of the form. On lg+ it is hidden: Save / Cancel sit in the right-hand column (x-admin.form-actions,
    which is hidden below lg — one Save per screen size).
    The slot goes on the left, e.g. the boat form's checklist counter.

    <x-admin.form-bar :submit="$editing ? 'Save changes' : 'Add boat'" :cancel="route('admin.boats.index')" />
--}}
@props(['submit' => 'Save changes', 'cancel' => null])

<div {{ $attributes->class('sticky bottom-0 z-20 -mx-4 flex items-center gap-2 border-t border-navy-100 bg-white/95 px-4 py-3 shadow-[0_-6px_20px_rgba(7,17,30,0.08)] backdrop-blur md:-mx-8 md:px-8 lg:hidden') }} data-form-bar>
    {{ $slot }}
    <div class="ml-auto flex shrink-0 gap-2">
        @if ($cancel)
            <x-button :href="$cancel" variant="outline">Cancel</x-button>
        @endif
        <x-button type="submit" variant="dark">{{ $submit }}</x-button>
    </div>
</div>
