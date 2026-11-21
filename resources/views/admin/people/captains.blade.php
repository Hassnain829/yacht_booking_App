@extends('layouts.admin', ['title' => 'Captains', 'active' => 'captains'])

@section('content')
    <x-admin.page-header title="Captains" description="Licensed captains you assign to trips. Their email and phone come from their own profile; assignment emails never include guest details.">
        <x-slot:actions>
            <x-button variant="dark" data-open-modal="captain-modal" icon-left="user-plus">Add captain</x-button>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card flush class="mt-6">
        <x-admin.table>
            <x-slot:head><th scope="col">Captain</th><th scope="col">Contact</th><th scope="col">License</th><th scope="col">Expires</th><th scope="col">Pay rate</th><th scope="col">Trips this month</th><th scope="col">Status</th></x-slot:head>
            @foreach ($captains as $c)
                <tr>
                    <td><div class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-navy-950 text-xs font-bold text-brass-300">{{ $c->initials }}</span><span class="font-bold text-navy-950">{{ $c->name }}</span></div></td>
                    <td><span class="block">{{ $c->email }}</span><span class="text-xs text-navy-400">{{ $c->phone }}</span></td>
                    <td><span class="block">{{ $c->license_label }}</span><span class="font-mono text-xs text-navy-400">{{ $c->license_number }}</span></td>
                    <td class="whitespace-nowrap">{{ $c->license_expires->format('M j, Y') }}@if ($c->expiring_soon)<x-admin.pill tone="warning" class="ml-1">Soon</x-admin.pill>@endif</td>
                    <td class="font-semibold text-navy-950">{{ money($c->rate_cents, 'never') }}/hr</td>
                    <td>{{ $c->trips_month }}</td>
                    <td><x-admin.pill :tone="$c->active ? 'success' : 'muted'">{{ $c->active ? 'Active' : 'Inactive' }}</x-admin.pill></td>
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.card>

    @push('modals')
        <x-admin.modal id="captain-modal" title="Add a captain" wide>
            <form method="POST" action="#" class="grid gap-4 sm:grid-cols-2" data-demo-form="Demo: captains are saved once the database is connected (Phase 3).">
                @csrf
                <x-form.input name="name" id="cap-name" label="Full name" required maxlength="80" class="sm:col-span-2" />
                <x-form.input name="email" id="cap-email" type="email" label="Email (for trip assignments)" required maxlength="254" />
                <x-form.input name="phone" id="cap-phone" type="tel" label="Phone" required maxlength="30" />
                <x-form.select name="license_type" id="cap-license-type" label="License type" required :options="$licenseTypes" placeholder="Choose…" hint="OUPV (six-pack) captains can take at most 6 guests." />
                <x-form.input name="license_number" id="cap-license-number" label="License number" required maxlength="30" />
                <x-form.input name="license_expires" id="cap-license-expires" type="date" label="License expires" required />
                <x-form.input name="rate" id="cap-rate" label="Pay rate (USD per hour)" required inputmode="decimal" maxlength="7" />
                <div class="flex justify-end gap-2 sm:col-span-2"><x-button type="button" variant="outline" data-close-modal>Cancel</x-button><x-button type="submit" variant="dark">Save captain</x-button></div>
            </form>
        </x-admin.modal>
    @endpush
@endsection
