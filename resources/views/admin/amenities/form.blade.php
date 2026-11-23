@extends('layouts.admin', ['title' => $amenity->exists ? $amenity->name : 'Add an amenity', 'active' => 'amenities'])

@section('content')
    <x-admin.page-header :title="$amenity->exists ? $amenity->name : 'Add an amenity'" :back="['All amenities', route('admin.amenities.index')]"
        :description="$amenity->exists ? trans_choice('{0} No boat has it ticked.|{1} Ticked on 1 boat.|[2,*] Ticked on :count boats.', $amenity->boats_count) : null" />

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ $amenity->exists ? route('admin.amenities.update', $amenity) : route('admin.amenities.store') }}" class="space-y-6 lg:col-span-2">
            @csrf
            @if ($amenity->exists)
                @method('PUT')
            @endif
            <x-admin.card title="Details">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="name" label="Name" :value="$amenity->name" required maxlength="80" class="sm:col-span-2" />
                    <x-form.select name="group" label="List" :options="$labels" :value="$amenity->group?->value" required />
                    <x-form.select name="icon" label="Icon (optional)" :options="$icons" :value="$amenity->icon" placeholder="No icon" />
                    <x-form.input name="sort" label="Order" type="number" :value="$amenity->sort ?? 0" required min="0" max="9999" hint="Lower numbers come first." />
                </div>
            </x-admin.card>
            <div class="flex flex-col gap-2 sm:flex-row">
                <x-button type="submit" variant="dark" size="lg">{{ $amenity->exists ? 'Save changes' : 'Add amenity' }}</x-button>
                <x-button :href="route('admin.amenities.index')" variant="outline" size="lg">Cancel</x-button>
            </div>
        </form>

        @if ($amenity->exists)
            <x-admin.card title="Delete">
                <p class="text-sm leading-relaxed text-navy-600">Deleting removes this item from the list{{ $amenity->boats_count ? ' and from the '.trans_choice('{1}boat|[2,*]:count boats', $amenity->boats_count).' that have it ticked' : '' }}. It can't be undone.</p>
                <button type="button" data-open-modal="destroy-record" class="mt-4 inline-flex items-center gap-2 rounded-full border border-red-200 px-6 py-3 text-sm font-bold text-red-700 transition hover:bg-red-50"><i data-lucide="trash-2" class="h-4 w-4"></i>Delete</button>
            </x-admin.card>
            @push('modals')
                <x-admin.modal id="destroy-record" :title="'Delete '.$amenity->name" :open="old('dialog') === 'destroy-record' && $errors->getBag('sensitive')->any()">
                    <x-admin.sensitive-form id="destroy-record" :action="route('admin.amenities.destroy', $amenity)" method="DELETE" submit="Delete" danger />
                </x-admin.modal>
            @endpush
        @endif
    </div>
@endsection
