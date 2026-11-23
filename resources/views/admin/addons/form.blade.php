@extends('layouts.admin', ['title' => $addon->exists ? $addon->name : 'Add an add-on', 'active' => 'addons'])

@php
    $editing = $addon->exists;
    $trashed = $editing && $addon->trashed();
@endphp

@section('content')
    <x-admin.page-header :title="$editing ? $addon->name : 'Add an add-on'" :back="['All add-ons', route('admin.addons.index', $trashed ? ['view' => 'trash'] : [])]"
        description="Guests see the name, description and price in the booking wizard and at checkout.">
        @if ($trashed)
            <x-slot:actions><x-admin.pill tone="danger">In the Trash</x-admin.pill></x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($trashed)
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-admin.card title="Details" class="lg:col-span-2">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Price</dt><dd class="mt-1 text-navy-900">{{ money($addon->price_cents) }}{{ $addon->unit === \App\Enums\AddonUnit::PerGuest ? ' / guest' : '' }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Web address</dt><dd class="mt-1 font-mono text-navy-900">{{ $addon->slug }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Description</dt><dd class="mt-1 text-navy-900">{{ $addon->description ?: '—' }}</dd></div>
                </dl>
                <p class="mt-5 text-xs text-navy-500">Restore the add-on to edit it.</p>
            </x-admin.card>
            <x-admin.lifecycle :record="$addon" label="add-on" :delete-blockers="$deleteBlockers" :routes="[
                'restore' => route('admin.addons.restore', $addon),
                'destroy' => route('admin.addons.destroy', $addon),
            ]" />
        </div>
    @else
        <form method="POST" action="{{ $editing ? route('admin.addons.update', $addon) : route('admin.addons.store') }}" class="mt-6 grid gap-6 lg:grid-cols-3">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="min-w-0 space-y-6 lg:col-span-2">
                <x-admin.card title="Details">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="name" label="Name" :value="$addon->name" required maxlength="120" placeholder="e.g. Champagne toast" class="sm:col-span-2" />
                        <x-form.input name="description" label="Short description" :value="$addon->description" maxlength="255" placeholder="e.g. Two bottles of Moët on ice" class="sm:col-span-2" />
                        <x-form.select name="icon" label="Icon" :options="$icons" :value="$addon->icon" required />
                        <x-form.input name="slug" label="Web address" :value="$addon->slug" maxlength="80" placeholder="made from the name" hint="Leave empty to make it from the name." />
                    </div>
                </x-admin.card>

                <x-admin.card title="Price">
                    <div class="grid gap-5 sm:grid-cols-3">
                        <x-form.input name="price" label="Price (USD)" :value="\App\Support\Money::input($addon->price_cents)" required inputmode="decimal" maxlength="10" placeholder="35" />
                        <x-form.select name="unit" label="Charged" :options="$units" :value="$addon->unit?->value" required />
                        <x-form.input name="max_quantity" type="number" label="Max per booking" :value="$addon->max_quantity" required min="1" max="99" />
                    </div>
                </x-admin.card>
            </div>

            <x-admin.form-aside>
                <x-admin.card title="On the site">
                    <div class="space-y-5">
                        <x-form.checkbox name="is_active" hidden-zero :checked="$addon->is_active">Offer this add-on<span class="block text-xs text-navy-500">Hidden add-ons can't be added to new bookings.</span></x-form.checkbox>
                        <x-form.input name="sort" label="Order" type="number" :value="$addon->sort" required min="0" max="9999" hint="Lower numbers come first." />
                    </div>
                </x-admin.card>

                <x-admin.form-actions :submit="$editing ? 'Save changes' : 'Add add-on'" :cancel="route('admin.addons.index')" />

                @if ($editing)
                    <x-admin.lifecycle :record="$addon" label="add-on" :trash-blockers="$trashBlockers" :routes="['trash' => route('admin.addons.trash', $addon)]" />
                @endif
            </x-admin.form-aside>

            <x-admin.form-bar :submit="$editing ? 'Save changes' : 'Add add-on'" :cancel="route('admin.addons.index')" />
        </form>
    @endif
@endsection
