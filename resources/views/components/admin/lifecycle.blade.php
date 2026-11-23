{{--
    The Trash card on a record's admin page (PLAN 3.4, TrashService): Move to Trash, or — once in the Trash —
    Restore and Delete permanently. Blockers are listed before the admin tries; each action opens a dialog
    that asks for a reason and the password.

    <x-admin.lifecycle :record="$harbor" label="harbor" :routes="['trash' => …, 'restore' => …, 'destroy' => …]"
                       :trash-blockers="$trashBlockers" :delete-blockers="$deleteBlockers" />
--}}
@props(['record', 'label', 'routes', 'trashBlockers' => [], 'deleteBlockers' => []])

@php
    $bag = $errors->getBag('sensitive');
    $reopen = fn (string $dialog) => old('dialog') === $dialog && $bag->any();
@endphp

<x-admin.card :title="$record->trashed() ? 'In the Trash' : 'Trash'" {{ $attributes }}>
    @if ($record->trashed())
        <p class="text-sm leading-relaxed text-navy-600">This {{ $label }} was moved to the Trash on {{ $record->deleted_at->timezone(config('app.display_timezone'))->format('M j, Y \a\t g:i A') }}. It is hidden everywhere on the site. Restore it to use it again, or delete it permanently.</p>
        @if ($deleteBlockers)
            <div class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200">
                <p class="font-bold">It can't be deleted permanently yet:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach ($deleteBlockers as $blocker)<li>{{ $blocker }}</li>@endforeach
                </ul>
            </div>
        @endif
        <div class="mt-4 flex flex-wrap gap-2">
            <x-button variant="dark" data-open-modal="restore-record" icon-left="undo-2">Restore</x-button>
            <button type="button" data-open-modal="destroy-record" @disabled($deleteBlockers) class="inline-flex items-center justify-center gap-2 rounded-full border border-red-200 px-6 py-3 text-sm font-bold text-red-700 transition hover:bg-red-50 disabled:pointer-events-none disabled:opacity-50"><i data-lucide="trash-2" class="h-4 w-4"></i>Delete permanently</button>
        </div>
    @else
        <p class="text-sm leading-relaxed text-navy-600">Moving this {{ $label }} to the Trash hides it everywhere. Nothing is lost: you can restore it from the Trash tab at any time.</p>
        @if ($trashBlockers)
            <div class="mt-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-900 ring-1 ring-amber-200">
                <p class="font-bold">It can't go to the Trash right now:</p>
                <ul class="mt-1 list-disc space-y-0.5 pl-5">
                    @foreach ($trashBlockers as $blocker)<li>{{ $blocker }}</li>@endforeach
                </ul>
            </div>
        @endif
        <div class="mt-4">
            <button type="button" data-open-modal="trash-record" @disabled($trashBlockers) class="inline-flex items-center justify-center gap-2 rounded-full border border-red-200 px-6 py-3 text-sm font-bold text-red-700 transition hover:bg-red-50 disabled:pointer-events-none disabled:opacity-50"><i data-lucide="trash-2" class="h-4 w-4"></i>Move to Trash</button>
        </div>
    @endif
</x-admin.card>

@push('modals')
    @if ($record->trashed())
        <x-admin.modal id="restore-record" :title="'Restore '.$record->trashTitle()" :open="$reopen('restore-record')">
            <x-admin.sensitive-form id="restore-record" :action="$routes['restore']" submit="Restore" />
        </x-admin.modal>
        <x-admin.modal id="destroy-record" :title="'Delete '.$record->trashTitle().' permanently'" :open="$reopen('destroy-record')">
            <x-admin.sensitive-form id="destroy-record" :action="$routes['destroy']" method="DELETE" submit="Delete permanently" danger>
                <p class="rounded-xl bg-red-50 p-3 text-sm leading-relaxed text-red-800 ring-1 ring-red-200">This removes the {{ $label }} and its files for good. It can't be undone.</p>
            </x-admin.sensitive-form>
        </x-admin.modal>
    @else
        <x-admin.modal id="trash-record" :title="'Move '.$record->trashTitle().' to the Trash'" :open="$reopen('trash-record')">
            <x-admin.sensitive-form id="trash-record" :action="$routes['trash']" method="DELETE" submit="Move to Trash" danger />
        </x-admin.modal>
    @endif
@endpush
