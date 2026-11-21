@extends('layouts.admin', ['title' => 'Audit log', 'active' => 'audit'])

@section('content')
    <x-admin.page-header title="Audit log" description="Every sensitive action — refunds, payouts, deductions, setting changes, role changes — with who did it, when and why. Entries can't be edited or deleted." />

    <x-admin.card flush class="mt-6">
        <x-admin.table>
            <x-slot:head><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Details</th></x-slot:head>
            @foreach ($entries as $e)
                <tr>
                    <td class="whitespace-nowrap">{{ $e->at->format('M j, Y g:i A') }}</td>
                    <td>@if ($e->actor === 'System')<x-admin.pill tone="muted">System</x-admin.pill>@else{{ $e->actor }}@endif</td>
                    <td class="font-semibold text-navy-950">{{ $e->action }}</td>
                    <td class="text-navy-600">{{ $e->subject }}</td>
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.card>
@endsection
