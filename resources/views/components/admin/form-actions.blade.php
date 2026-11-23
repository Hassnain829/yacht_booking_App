{{--
    The Save / Cancel buttons of an admin record form on lg+ (3.7), inside its right-hand column
    (x-admin.form-aside). When that column is taller than the screen they stick to its bottom edge (the cards
    scroll under a short fade), so Save is always one click away. Below lg they are hidden: the form's
    x-admin.form-bar (sticky at the bottom of the screen) takes over.

    <x-admin.form-actions :submit="$editing ? 'Save changes' : 'Add boat'" :cancel="route('admin.boats.index')" />
--}}
@props(['submit' => 'Save changes', 'cancel' => null])

<div {{ $attributes->class('hidden gap-2 lg:sticky lg:bottom-0 lg:z-10 lg:flex lg:flex-col lg:bg-shell lg:pt-3 lg:pb-1 lg:before:pointer-events-none lg:before:absolute lg:before:inset-x-0 lg:before:-top-6 lg:before:h-6 lg:before:bg-gradient-to-t lg:before:from-shell lg:before:to-transparent xl:flex-row') }} data-form-actions>
    <x-button type="submit" variant="dark" size="lg" class="flex-1">{{ $submit }}</x-button>
    @if ($cancel)
        <x-button :href="$cancel" variant="outline" size="lg">Cancel</x-button>
    @endif
</div>
