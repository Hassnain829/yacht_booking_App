@extends('layouts.admin', ['title' => $harbor->exists ? $harbor->name : 'Add a harbor', 'active' => 'harbors'])

@php
    $editing = $harbor->exists;
    $trashed = $editing && $harbor->trashed();
@endphp

@section('content')
    <x-admin.page-header :title="$editing ? $harbor->name : 'Add a harbor'" :back="['All harbors', route('admin.harbors.index', $trashed ? ['view' => 'trash'] : [])]"
        :description="$editing ? trans_choice('{0} No boats depart from here yet.|{1} 1 boat departs from here.|[2,*] :count boats depart from here.', $harbor->boats_count) : 'Guests see the name, area and photo; the map uses the search text or the coordinates.'">
        @if ($trashed)
            <x-slot:actions><x-admin.pill tone="danger">In the Trash</x-admin.pill></x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($trashed)
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-admin.card title="Details" class="lg:col-span-2">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Area</dt><dd class="mt-1 text-navy-900">{{ $harbor->area ?: '—' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Web address</dt><dd class="mt-1 font-mono text-navy-900">{{ $harbor->slug }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Address</dt><dd class="mt-1 text-navy-900">{{ $harbor->address ?: '—' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Boarding notes</dt><dd class="mt-1 text-navy-900">{{ $harbor->notes ?: '—' }}</dd></div>
                </dl>
                <p class="mt-5 text-xs text-navy-500">Restore the harbor to edit it.</p>
            </x-admin.card>
            <x-admin.lifecycle :record="$harbor" label="harbor" :delete-blockers="$deleteBlockers" :routes="[
                'restore' => route('admin.harbors.restore', $harbor),
                'destroy' => route('admin.harbors.destroy', $harbor),
            ]" />
        </div>
    @else
        <form method="POST" action="{{ $editing ? route('admin.harbors.update', $harbor) : route('admin.harbors.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-6 lg:grid-cols-3">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="min-w-0 space-y-6 lg:col-span-2">
                <x-admin.card title="Details">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="name" label="Name" :value="$harbor->name" required maxlength="120" placeholder="e.g. Navy Pier" class="sm:col-span-2" />
                        <x-form.input name="area" label="Area" :value="$harbor->area" maxlength="120" placeholder="e.g. Streeterville" />
                        <x-form.input name="slug" label="Web address" :value="$harbor->slug" maxlength="80" placeholder="made from the name" hint="Lowercase letters, numbers and dashes. Leave empty to make it from the name." />
                        <x-form.input name="address" label="Street address" :value="$harbor->address" maxlength="255" class="sm:col-span-2" />
                        <x-form.textarea name="notes" label="Boarding notes for guests" :value="$harbor->notes" rows="3" maxlength="1000" placeholder="e.g. Gate 3, follow the signs to Dock D." class="sm:col-span-2" />
                    </div>
                </x-admin.card>

                <x-admin.card title="Map">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="map_query" label="Map search text" :value="$harbor->map_query" maxlength="255" placeholder="e.g. Navy Pier, Chicago, IL" hint="What the Google map searches for." class="sm:col-span-2" />
                        <x-form.input name="lat" label="Latitude (optional)" :value="$harbor->lat" inputmode="decimal" maxlength="12" placeholder="41.891800" />
                        <x-form.input name="lng" label="Longitude (optional)" :value="$harbor->lng" inputmode="decimal" maxlength="12" placeholder="-87.605100" />
                    </div>
                </x-admin.card>
            </div>

            <div class="min-w-0 space-y-6">
                <x-admin.card title="Photo">
                    <x-form.file name="image" label="{{ $harbor->image_path ? 'Replace the photo' : 'Photo' }}" :preview="$harbor->imageUrl()" :preview-alt="$harbor->name" remove-label="Remove the photo"
                        hint="JPG, PNG, WEBP or GIF up to {{ (int) config('media.max_kb') / 1024 }} MB, at least {{ config('media.image.min_width') }} × {{ config('media.image.min_height') }} px. Saved as WebP; location data is removed." />
                </x-admin.card>

                <x-admin.card title="On the site">
                    <div class="space-y-5">
                        <x-form.checkbox name="is_active" hidden-zero :checked="$harbor->is_active">Show this harbor<span class="block text-xs text-navy-500">Hidden harbors can't be chosen for new boats.</span></x-form.checkbox>
                        <x-form.input name="sort" label="Order" type="number" :value="$harbor->sort" required min="0" max="9999" hint="Lower numbers come first." />
                    </div>
                </x-admin.card>

                <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                    <x-button type="submit" variant="dark" size="lg">{{ $editing ? 'Save changes' : 'Add harbor' }}</x-button>
                    <x-button :href="route('admin.harbors.index')" variant="outline" size="lg">Cancel</x-button>
                </div>

                @if ($editing)
                    {{-- Last on every screen size. Only buttons here (type="button"); its dialogs and their forms are rendered outside this form. --}}
                    <x-admin.lifecycle :record="$harbor" label="harbor" :trash-blockers="$trashBlockers" :routes="['trash' => route('admin.harbors.trash', $harbor)]" />
                @endif
            </div>
        </form>
    @endif
@endsection
