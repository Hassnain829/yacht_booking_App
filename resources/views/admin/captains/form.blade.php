@extends('layouts.admin', ['title' => $captain->exists ? $captain->name : 'Add a captain', 'active' => 'captains'])

@php
    $editing = $captain->exists;
    $trashed = $editing && $captain->trashed();
@endphp

@section('content')
    <x-admin.page-header :title="$editing ? $captain->name : 'Add a captain'" :back="['All captains', route('admin.captains.index', $trashed ? ['view' => 'trash'] : [])]"
        description="Guests see the name, photo and bio on their trip page. Email, phone, license and pay stay internal.">
        @if ($trashed)
            <x-slot:actions><x-admin.pill tone="danger">In the Trash</x-admin.pill></x-slot:actions>
        @elseif ($editing && $captain->licenseExpired())
            <x-slot:actions><x-admin.pill tone="danger">License expired</x-admin.pill></x-slot:actions>
        @elseif ($editing && $captain->licenseExpiresSoon())
            <x-slot:actions><x-admin.pill tone="warning">License expires soon</x-admin.pill></x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($trashed)
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-admin.card title="Details" class="lg:col-span-2">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Email</dt><dd class="mt-1 text-navy-900">{{ $captain->email }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">License</dt><dd class="mt-1 text-navy-900">{{ $captain->license_type->label() }} · {{ $captain->maskedLicense() ?? '—' }}</dd></div>
                </dl>
                <p class="mt-5 text-xs text-navy-500">Restore the captain to edit the profile.</p>
            </x-admin.card>
            <x-admin.lifecycle :record="$captain" label="captain" :delete-blockers="$deleteBlockers" :routes="[
                'restore' => route('admin.captains.restore', $captain),
                'destroy' => route('admin.captains.destroy', $captain),
            ]" />
        </div>
    @else
        <form method="POST" action="{{ $editing ? route('admin.captains.update', $captain) : route('admin.captains.store') }}" enctype="multipart/form-data" class="mt-6 grid gap-6 lg:grid-cols-3">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="min-w-0 space-y-6 lg:col-span-2">
                <x-admin.card title="Profile">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="name" label="Full name" :value="$captain->name" required maxlength="120" class="sm:col-span-2" />
                        <x-form.input name="email" type="email" label="Email (the captain's own)" :value="$captain->email" required maxlength="254" hint="Trip offers and assignments go here." />
                        <x-form.input name="phone" type="tel" label="Phone" :value="$captain->phone" maxlength="30" />
                        <x-form.textarea name="bio" label="Bio (guests see it)" :value="$captain->bio" rows="4" maxlength="2000" class="sm:col-span-2" />
                        <x-form.input name="years_experience" type="number" label="Years of experience" :value="$captain->years_experience" min="0" max="70" />
                        <x-form.input name="languages_text" label="Languages" :value="implode(', ', $captain->languages ?? [])" maxlength="200" placeholder="English, Spanish" hint="Separated by commas." :error="$errors->first('languages') ?: $errors->first('languages.*')" />
                        <x-form.input name="slug" label="Web address" :value="$captain->slug" maxlength="120" placeholder="made from the name" hint="Leave empty to make it from the name." class="sm:col-span-2" />
                    </div>
                </x-admin.card>

                <x-admin.card title="License">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.select name="license_type" label="License type" :options="$licenseTypes" :value="$captain->license_type?->value" required hint="OUPV (six-pack) captains can take at most 6 guests." />
                        <x-form.input name="license_number" label="License number" maxlength="40" autocomplete="off" :required="! $editing"
                            :placeholder="$captain->maskedLicense() ?? ''" :hint="$editing ? 'Stored encrypted. Leave empty to keep the saved number.' : 'Stored encrypted; never shown in full again.'" />
                        <x-form.input name="license_expires_on" type="date" label="License expires" :value="$captain->license_expires_on?->toDateString()" required />
                        <x-form.input name="max_passengers_licensed" type="number" label="Passengers allowed" :value="$captain->max_passengers_licensed" required min="1" max="150" />
                    </div>
                </x-admin.card>
            </div>

            <x-admin.form-aside>
                <x-admin.card title="Photo">
                    <x-form.file name="photo" label="{{ $captain->photo_path ? 'Replace the photo' : 'Photo' }}" :preview="$captain->photoUrl()" :preview-alt="$captain->name" remove-label="Remove the photo" hint="JPG, PNG, WEBP or GIF, at least 300 × 300 px." />
                </x-admin.card>

                <x-admin.card title="Pay and status">
                    <div class="space-y-5">
                        <x-form.input name="pay_rate" label="Pay rate (USD per hour)" :value="\App\Support\Money::input($captain->pay_rate_cents)" required inputmode="decimal" maxlength="8" hint="Internal; never shown to guests." />
                        <x-form.checkbox name="is_active" hidden-zero :checked="$captain->is_active">Active<span class="block text-xs text-navy-500">Inactive captains can't be assigned.</span></x-form.checkbox>
                        <x-form.checkbox name="is_public" hidden-zero :checked="$captain->is_public">Show on the site (About page)</x-form.checkbox>
                        <x-form.input name="sort" label="Order" type="number" :value="$captain->sort" required min="0" max="9999" />
                    </div>
                </x-admin.card>

                <x-admin.form-actions :submit="$editing ? 'Save changes' : 'Add captain'" :cancel="route('admin.captains.index')" />

                @if ($editing)
                    <x-admin.lifecycle :record="$captain" label="captain" :trash-blockers="$trashBlockers" :routes="['trash' => route('admin.captains.trash', $captain)]" />
                @endif
            </x-admin.form-aside>

            <x-admin.form-bar :submit="$editing ? 'Save changes' : 'Add captain'" :cancel="route('admin.captains.index')" />
        </form>

        @if ($editing)
            <x-admin.card title="License documents" id="documents" class="mt-6 scroll-mt-24 lg:max-w-3xl">
                <p class="text-xs text-navy-500">Private: only you (and later the captain) can open them.</p>
                @if ($captain->documents->isNotEmpty())
                    <ul class="mt-4 divide-y divide-navy-100">
                        @foreach ($captain->documents as $document)
                            <li class="flex items-center gap-3 py-3">
                                <i data-lucide="file-text" class="h-5 w-5 shrink-0 text-navy-400"></i>
                                <a href="{{ route('files.show', $document->uuid) }}" class="min-w-0 flex-1 truncate text-sm font-semibold text-navy-950 hover:underline" target="_blank" rel="noopener">{{ $document->original_name }}</a>
                                <span class="text-xs text-navy-400">{{ $document->created_at->timezone(config('app.display_timezone'))->format('M j, Y') }}</span>
                                <form method="POST" action="{{ route('admin.captains.documents.destroy', [$captain, $document->uuid]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded-lg p-1.5 text-red-700 hover:bg-red-50" aria-label="Delete {{ $document->original_name }}"><i data-lucide="x" class="h-4 w-4"></i></button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <form method="POST" action="{{ route('admin.captains.documents.store', $captain) }}" enctype="multipart/form-data" class="mt-4 flex flex-col gap-3 rounded-xl bg-navy-50 p-4 sm:flex-row sm:items-end">
                    @csrf
                    <x-form.file name="document" label="Upload a document" accept="application/pdf,image/jpeg,image/png,image/webp" :error="$errors->document->first('document')" hint="PDF or image, up to {{ (int) config('media.max_kb') / 1024 }} MB." class="flex-1" />
                    <x-button type="submit" variant="dark" icon-left="upload">Upload</x-button>
                </form>
            </x-admin.card>
        @endif
    @endif
@endsection
