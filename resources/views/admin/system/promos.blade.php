@extends('layouts.admin', ['title' => 'Promo codes', 'active' => 'promos'])

@section('content')
    <x-admin.page-header title="Promo codes" description="Discount codes guests enter at checkout. Codes become editable with the booking engine (Phase 4.7); these are the demo codes." />

    <x-admin.card flush class="mt-6">
        <x-admin.table min="min-w-[28rem]">
            <x-slot:head><th scope="col">Code</th><th scope="col">Discount</th><th scope="col">Applies to</th></x-slot:head>
            @foreach ($promos as $promo)
                <tr><td class="font-mono font-bold text-navy-950">{{ $promo['code'] }}</td><td>{{ $promo['label'] }}</td><td>{{ ucfirst($promo['applies_to']) }}</td></tr>
            @endforeach
        </x-admin.table>
    </x-admin.card>
@endsection
