{{-- Modal (<dialog>), opened by <button data-open-modal="{id}"> (ui/admin.js). --}}
@props(['id', 'title', 'wide' => false])

<dialog id="{{ $id }}" aria-labelledby="{{ $id }}-title" @class(['m-auto w-[calc(100%-2rem)] rounded-2xl bg-white p-0 text-navy-900 shadow-2xl backdrop:bg-navy-950/60', 'max-w-2xl' => $wide, 'max-w-lg' => ! $wide])>
    <div class="flex items-center justify-between gap-4 border-b border-navy-100 px-6 py-4">
        <h2 id="{{ $id }}-title" class="text-lg font-extrabold text-navy-950">{{ $title }}</h2>
        <button type="button" data-close-modal class="grid h-9 w-9 place-items-center rounded-lg text-navy-500 hover:bg-navy-50" aria-label="Close"><i data-lucide="x" class="h-4 w-4"></i></button>
    </div>
    <div class="px-6 py-5">{{ $slot }}</div>
</dialog>
