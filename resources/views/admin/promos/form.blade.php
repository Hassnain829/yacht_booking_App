@extends('layouts.admin', ['title' => $promo->exists ? $promo->code : 'Add a promo code', 'active' => 'promos'])

{{-- Add / edit a promo code (4.8). The 3.4 record form: aside with Save, lifecycle card, phone Save bar. --}}
@php
    $editing = $promo->exists;
    $trashed = $editing && $promo->trashed();
    $tz = config('app.display_timezone');
    $percent = $promo->type === App\Enums\PromoType::Percent;
    $amount = $promo->value === null ? '' : ($percent ? rtrim(rtrim(number_format($promo->value / 100, 2, '.', ''), '0'), '.') : App\Support\Money::input($promo->value));
@endphp

@section('content')
    <x-admin.page-header :title="$editing ? $promo->code : 'Add a promo code'" :back="['All promo codes', route('admin.promo-codes.index', $trashed ? ['view' => 'trash'] : [])]"
        :description="$editing ? trans_choice('{0} Not used yet.|{1} Used on 1 booking.|[2,*] Used on :count bookings.', $promo->redemptions_count) : 'Guests type the code at checkout. It takes the discount off the yacht charter or the whole booking (before tax).'">
        @if ($trashed)
            <x-slot:actions><x-admin.pill tone="danger">In the Trash</x-admin.pill></x-slot:actions>
        @endif
    </x-admin.page-header>

    @if ($trashed)
        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-admin.card title="Details" class="lg:col-span-2">
                <dl class="grid gap-4 text-sm sm:grid-cols-2">
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Discount</dt><dd class="mt-1 text-navy-900">{{ $promo->terms()->label() }}</dd></div>
                    <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Used</dt><dd class="mt-1 text-navy-900">{{ $promo->redemptions_count }}</dd></div>
                </dl>
                <p class="mt-5 text-xs text-navy-500">Restore the code to edit it. While it is in the Trash, guests can't use it.</p>
            </x-admin.card>
            <x-admin.lifecycle :record="$promo" label="promo code" :delete-blockers="$deleteBlockers" :routes="[
                'restore' => route('admin.promo-codes.restore', $promo),
                'destroy' => route('admin.promo-codes.destroy', $promo),
            ]" />
        </div>
    @else
        <form method="POST" action="{{ $editing ? route('admin.promo-codes.update', $promo) : route('admin.promo-codes.store') }}" class="mt-6 grid gap-6 lg:grid-cols-3">
            @csrf
            @if ($editing)
                @method('PUT')
            @endif

            <div class="min-w-0 space-y-6 lg:col-span-2">
                <x-admin.card title="Code and discount">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="code" label="Code" :value="$promo->code" required maxlength="30" placeholder="e.g. SUMMER25" hint="Letters and numbers. Guests can type it in any case." class="sm:col-span-2" />
                        <x-form.select name="type" label="Discount" :options="$types" :value="$promo->type?->value" required />
                        <x-form.input name="amount" label="Amount" :value="$amount" required inputmode="decimal" maxlength="10" placeholder="10" hint="A percentage (e.g. 10) or dollars (e.g. 50)." />
                        <x-form.select name="applies_to" label="Taken off" :options="$appliesTo" :value="$promo->applies_to?->value" required class="sm:col-span-2" />
                    </div>
                </x-admin.card>

                <x-admin.card title="Limits (optional)">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <x-form.input name="min_subtotal" label="Minimum booking (USD)" :value="App\Support\Money::input($promo->min_subtotal_cents)" inputmode="decimal" maxlength="10" placeholder="e.g. 1000" hint="Before tax. Below it the code gives nothing." />
                        <x-form.input name="max_discount" label="Largest discount (USD)" :value="App\Support\Money::input($promo->max_discount_cents)" inputmode="decimal" maxlength="10" placeholder="e.g. 200" hint="Percentage codes only." />
                        <x-form.input name="starts_on" type="date" label="First day" :value="$promo->starts_at?->setTimezone($tz)->toDateString()" />
                        <x-form.input name="ends_on" type="date" label="Last day" :value="$promo->ends_at?->setTimezone($tz)->toDateString()" hint="Chicago time, until the end of that day." />
                        <x-form.input name="max_redemptions" type="number" label="Uses in total" :value="$promo->max_redemptions" min="1" max="1000000" placeholder="No limit" />
                        <x-form.input name="max_per_user" type="number" label="Uses per guest" :value="$promo->max_per_user" min="1" max="100" placeholder="No limit" />
                    </div>
                </x-admin.card>
            </div>

            <x-admin.form-aside>
                <x-admin.card title="On the site">
                    <x-form.checkbox name="is_active" hidden-zero :checked="$promo->is_active">Guests can use this code<span class="block text-xs text-navy-500">Turn it off to pause the code without deleting it.</span></x-form.checkbox>
                </x-admin.card>

                <x-admin.form-actions :submit="$editing ? 'Save changes' : 'Add promo code'" :cancel="route('admin.promo-codes.index')" />

                @if ($editing)
                    <x-admin.lifecycle :record="$promo" label="promo code" :trash-blockers="$trashBlockers" :routes="['trash' => route('admin.promo-codes.trash', $promo)]" />
                @endif
            </x-admin.form-aside>

            <x-admin.form-bar :submit="$editing ? 'Save changes' : 'Add promo code'" :cancel="route('admin.promo-codes.index')" />
        </form>
    @endif
@endsection
