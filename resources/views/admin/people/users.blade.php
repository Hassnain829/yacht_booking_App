@extends('layouts.admin', ['title' => 'Users', 'active' => 'users'])

@section('content')
    <x-admin.page-header title="Users" description="Customers, boat owners and admins. An account becomes an owner when it lists a boat." />

    <nav class="mt-6 inline-flex flex-wrap gap-1 rounded-full bg-white p-1 text-xs font-bold shadow-xs ring-1 ring-navy-100" aria-label="Role">
        @foreach ([null => 'Everyone', 'customer' => 'Customers', 'owner' => 'Owners', 'admin' => 'Admins'] as $key => $label)
            <a href="{{ route('admin.users.index', $key ? ['role' => $key] : []) }}" @class(['rounded-full px-4 py-2', 'bg-navy-950 text-white' => $role === ($key ?: null), 'text-navy-500 hover:text-navy-950' => $role !== ($key ?: null)])>{{ $label }}</a>
        @endforeach
    </nav>

    <x-admin.card flush class="mt-6">
        <x-admin.table>
            <x-slot:head><th scope="col">Name</th><th scope="col">Role</th><th scope="col">Joined</th><th scope="col">Bookings</th><th scope="col">2FA</th><th scope="col">Status</th></x-slot:head>
            @foreach ($users as $u)
                <tr>
                    <td><span class="font-bold text-navy-950">{{ $u->name }}</span><span class="block text-xs text-navy-400">{{ $u->email }}</span></td>
                    <td><x-admin.pill :tone="match ($u->role) { 'admin' => 'navy', 'owner' => 'brass', default => 'muted' }">{{ ucfirst($u->role) }}</x-admin.pill></td>
                    <td class="whitespace-nowrap">{{ $u->joined_at->format('M j, Y') }}</td>
                    <td>{{ $u->bookings }}</td>
                    <td>@if ($u->two_factor)<i data-lucide="shield-check" class="h-4 w-4 text-emerald-600" aria-label="On"></i>@else<span class="text-navy-300">Off</span>@endif</td>
                    <td><x-admin.pill :tone="$u->status === 'active' ? 'success' : 'danger'">{{ ucfirst($u->status) }}</x-admin.pill></td>
                </tr>
            @endforeach
        </x-admin.table>
    </x-admin.card>
@endsection
