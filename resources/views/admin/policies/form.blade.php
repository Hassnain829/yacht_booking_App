@extends('layouts.admin', ['title' => $policy->name, 'active' => 'policies'])

@php
    $submitted = old('name') !== null;
    $rows = $submitted
        ? collect((array) old('rules', []))->map(fn ($r) => ['hours' => $r['hours'] ?? '', 'pct' => $r['pct'] ?? ''])->values()->all()
        : collect($policy->rules)->map(fn ($r) => ['hours' => $r['min_hours_before'], 'pct' => $r['refund_pct']])->all();
    $rows = array_pad($rows, 6, ['hours' => '', 'pct' => '']);
    $field = 'w-full rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-950 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
@endphp

@section('content')
    <x-admin.page-header :title="$policy->name.' policy'" :back="['All policies', route('admin.policies.index')]" description="Changes apply to new bookings only. Bookings already made keep the rules they were made with." />

    <form method="POST" action="{{ route('admin.policies.update', $policy) }}" class="mt-6 grid gap-6 lg:grid-cols-3">
        @csrf
        @method('PUT')
        <div class="min-w-0 space-y-6 lg:col-span-2">
            <x-admin.card title="Refund rules">
                <p class="text-sm text-navy-600">For each row: if the guest cancels at least this many hours before the start, they get this much back. Add a 0-hour row for the last part. Empty rows are ignored.</p>
                @if ($errors->first('rules') ?: collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'rules.'))->flatten()->first())
                    <p class="mt-3 rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-700 ring-1 ring-red-200" role="alert">{{ $errors->first('rules') ?: collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'rules.'))->flatten()->first() }}</p>
                @endif
                <div class="mt-4 space-y-2">
                    @foreach ($rows as $i => $row)
                        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3">
                            <label class="sr-only" for="rule-hours-{{ $i }}">Row {{ $i + 1 }}: hours before the start</label>
                            <div class="relative"><input id="rule-hours-{{ $i }}" name="rules[{{ $i }}][hours]" type="number" min="0" max="8760" value="{{ $row['hours'] }}" class="{{ $field }} pr-24"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-navy-400">hours before</span></div>
                            <i data-lucide="arrow-right" class="h-4 w-4 text-navy-300"></i>
                            <label class="sr-only" for="rule-pct-{{ $i }}">Row {{ $i + 1 }}: refund percent</label>
                            <div class="relative"><input id="rule-pct-{{ $i }}" name="rules[{{ $i }}][pct]" type="number" min="0" max="100" value="{{ $row['pct'] }}" class="{{ $field }} pr-16"><span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-navy-400">% back</span></div>
                        </div>
                    @endforeach
                </div>
            </x-admin.card>

            <x-admin.card title="What guests read">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="name" label="Name" :value="$policy->name" required maxlength="80" />
                    <x-form.input name="badge" label="Badge (optional)" :value="$policy->badge" maxlength="60" placeholder="e.g. Recommended" />
                    <x-form.textarea name="points_text" label="Sentences (one per line)" :value="implode(PHP_EOL, $policy->points ?? [])" rows="4" maxlength="2000" :error="$errors->first('points') ?: $errors->first('points.*')" hint="Shown in the listing wizard, on boat pages and at checkout. Up to 6 lines." class="sm:col-span-2" />
                    <x-form.checkbox name="is_active" hidden-zero :checked="$policy->is_active" class="sm:col-span-2">Offer this policy<span class="block text-xs text-navy-500">Boats that use it keep it; new boats can't choose it when it is off.</span></x-form.checkbox>
                </div>
            </x-admin.card>

            <x-admin.card title="Confirm">
                <x-admin.confirm-fields placeholder="Why are you changing the refund rules?" />
            </x-admin.card>
        </div>
        <div class="min-w-0">
            <div class="flex flex-col gap-2 sm:flex-row lg:flex-col">
                <x-button type="submit" variant="dark" size="lg">Save policy</x-button>
                <x-button :href="route('admin.policies.index')" variant="outline" size="lg">Cancel</x-button>
            </div>
        </div>
    </form>
@endsection
