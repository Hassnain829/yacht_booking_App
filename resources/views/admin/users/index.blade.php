@extends('layouts.admin', ['title' => 'Users', 'active' => 'users'])

@php
    $field = 'rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
    $tz = config('app.display_timezone');
    $roleTones = ['admin' => 'navy', 'owner' => 'brass', 'captain' => 'navy', 'customer' => 'muted'];
@endphp

@section('content')
    <x-admin.page-header title="Users" description="Every account. An account becomes an owner when it lists a boat. Open one to see its login history, suspend or ban it, or reset its two-factor login." />

    <nav class="mt-6 inline-flex flex-wrap gap-1 rounded-full bg-white p-1 text-xs font-bold shadow-xs ring-1 ring-navy-100" aria-label="Role">
        @foreach ([null => 'Everyone', 'customer' => 'Customers', 'owner' => 'Owners', 'admin' => 'Admins'] as $key => $label)
            <a href="{{ route('admin.users.index', array_filter(['role' => $key ?: null, 'status' => $filters['status'], 'q' => $filters['q'] ?: null])) }}" @class(['rounded-full px-4 py-2', 'bg-navy-950 text-white' => $filters['role'] === ($key ?: null), 'text-navy-500 hover:text-navy-950' => $filters['role'] !== ($key ?: null)]) @if ($filters['role'] === ($key ?: null)) aria-current="page" @endif>{{ $label }}@if ($key) <span class="ml-1 opacity-60">{{ $roleCounts[$key] ?? 0 }}</span>@endif</a>
        @endforeach
    </nav>

    <form method="GET" action="{{ route('admin.users.index') }}" class="mt-4 flex flex-wrap gap-2" role="search">
        @if ($filters['role'])<input type="hidden" name="role" value="{{ $filters['role'] }}">@endif
        <label for="filter-status" class="sr-only">Status</label>
        <select id="filter-status" name="status" class="{{ $field }}">
            <option value="">Any status</option>
            @foreach (\App\Enums\UserStatus::cases() as $s)
                <option value="{{ $s->value }}" @selected($filters['status'] === $s->value)>{{ $s->label() }}</option>
            @endforeach
        </select>
        <div class="relative min-w-0 flex-1 sm:w-72 sm:flex-none">
            <label for="filter-q" class="sr-only">Search users</label>
            <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
            <input id="filter-q" name="q" type="search" maxlength="60" value="{{ $filters['q'] }}" placeholder="Name or email" class="{{ $field }} w-full pl-10">
        </div>
        <x-button type="submit" variant="dark">Filter</x-button>
    </form>

    <x-admin.card flush class="mt-6">
        <x-admin.table>
            <x-slot:head>
                <x-admin.sort column="name" label="Name" :sort="$sort" :dir="$dir" />
                <th scope="col">Role</th>
                <x-admin.sort column="joined" label="Joined" :sort="$sort" :dir="$dir" />
                <x-admin.sort column="last_login" label="Last login" :sort="$sort" :dir="$dir" />
                <th scope="col">2FA</th>
                <th scope="col">Status</th>
                <th scope="col"><span class="sr-only">Open</span></th>
            </x-slot:head>
            @forelse ($users as $u)
                <tr>
                    <td><a href="{{ route('admin.users.show', $u) }}" class="font-bold text-navy-950 hover:underline">{{ $u->name }}</a><span class="block text-xs text-navy-400">{{ $u->email }}</span></td>
                    <td><x-admin.pill :tone="$roleTones[$u->role->value] ?? 'muted'">{{ $u->role->label() }}</x-admin.pill></td>
                    <td class="whitespace-nowrap">{{ $u->created_at->timezone($tz)->format('M j, Y') }}</td>
                    <td class="whitespace-nowrap text-navy-500">{{ $u->last_login_at?->timezone($tz)->format('M j, Y g:i A') ?? 'Never' }}</td>
                    <td>@if ($u->hasEnabledTwoFactorAuthentication())<i data-lucide="shield-check" class="h-4 w-4 text-emerald-600" aria-label="On"></i>@else<span class="text-navy-300">Off</span>@endif</td>
                    <td><x-admin.pill :tone="$u->status === \App\Enums\UserStatus::Active ? 'success' : 'danger'">{{ $u->status->label() }}</x-admin.pill></td>
                    <td class="text-right"><a href="{{ route('admin.users.show', $u) }}" class="inline-flex items-center gap-1 text-sm font-bold text-navy-600 hover:text-navy-950">Open<i data-lucide="chevron-right" class="h-4 w-4"></i></a></td>
                </tr>
            @empty
                <tr><td colspan="7" class="py-12 text-center text-sm text-navy-500">No accounts match these filters.</td></tr>
            @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$users" />
    </x-admin.card>
@endsection
