@extends('layouts.admin', ['title' => 'Payments', 'active' => 'payments'])

@section('content')
    <x-admin.page-header title="Payments" description="Charges from Stripe: deposits, balances and full payments. Failed balance charges need a follow-up with the guest.">
        <x-slot:actions><x-button variant="outline" data-soon icon-left="download">Export CSV</x-button></x-slot:actions>
    </x-admin.page-header>

    <x-admin.card flush class="mt-6">
        <x-admin.table>
            <x-slot:head><th scope="col">When</th><th scope="col">Booking</th><th scope="col">Guest</th><th scope="col">Type</th><th scope="col">Card</th><th scope="col">Status</th><th scope="col" class="text-right">Amount</th></x-slot:head>
            @foreach ($payments as $p)
                <tr>
                    <td class="whitespace-nowrap">{{ $p->at->format('M j, g:i A') }}</td>
                    <td><a href="{{ route('admin.bookings.show', $p->ref) }}" class="font-mono text-xs font-bold text-navy-950 hover:underline">{{ $p->ref }}</a></td>
                    <td>{{ $p->customer }}</td>
                    <td>{{ ucfirst($p->kind) }}</td>
                    <td class="whitespace-nowrap">{{ $p->card }}</td>
                    <td><x-admin.pill :tone="match ($p->status) { 'succeeded' => 'success', 'failed' => 'danger', default => 'muted' }">{{ ucfirst($p->status) }}</x-admin.pill>@if (! empty($p->failure))<span class="mt-1 block text-[11px] text-red-600">{{ $p->failure }}</span>@endif</td>
                    <td class="text-right font-bold text-navy-950">{{ money($p->amount_cents, 'always') }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.card>
@endsection
