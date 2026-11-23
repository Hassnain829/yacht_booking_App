@extends('layouts.admin', ['title' => 'Audit log', 'active' => 'audit'])

@php
    $field = 'rounded-xl border border-navy-200 bg-white px-3 py-2.5 text-sm text-navy-900 focus:border-navy-950 focus:outline-hidden focus:ring-4 focus:ring-navy-100';
    $tz = config('app.display_timezone');
    $show = fn ($value) => is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (is_bool($value) ? ($value ? 'yes' : 'no') : ($value === null || $value === '' ? '—' : (string) $value));
@endphp

@section('content')
    <x-admin.page-header title="Audit log" description="Every admin action, status change and money action — who did it, when, what changed and why. Entries can't be edited or deleted." />

    <form method="GET" action="{{ route('admin.audit.index') }}" class="mt-6 flex flex-wrap gap-2" role="search">
        <label for="filter-area" class="sr-only">Area</label>
        <select id="filter-area" name="area" class="{{ $field }}">
            <option value="">Everything</option>
            @foreach ($areas as $key => $label)
                <option value="{{ $key }}" @selected($filters['area'] === $key)>{{ $label }}</option>
            @endforeach
        </select>
        <label for="filter-who" class="sr-only">Who</label>
        <select id="filter-who" name="who" class="{{ $field }}">
            <option value="">Anyone</option>
            <option value="admin" @selected($filters['who'] === 'admin')>A person</option>
            <option value="system" @selected($filters['who'] === 'system')>The system</option>
        </select>
        <div class="relative min-w-0 flex-1 sm:w-72 sm:flex-none">
            <label for="filter-q" class="sr-only">Search actions and reasons</label>
            <i data-lucide="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
            <input id="filter-q" name="q" type="search" maxlength="60" value="{{ $filters['q'] }}" placeholder="Action or reason" class="{{ $field }} w-full pl-10">
        </div>
        <x-button type="submit" variant="dark">Filter</x-button>
        @if (array_filter($filters))
            <a href="{{ route('admin.audit.index') }}" class="px-2 py-2.5 text-sm font-bold text-navy-500 hover:text-navy-950">Clear</a>
        @endif
    </form>

    <x-admin.card flush class="mt-6">
        <x-admin.table min="min-w-[52rem]">
            <x-slot:head><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Record</th><th scope="col">Details</th></x-slot:head>
            @forelse ($entries as $entry)
                @php
                    $keys = array_unique(array_merge(array_keys($entry->before ?? []), array_keys($entry->after ?? [])));
                    $record = $entry->subject_type ? ($subjects[$entry->subject_type.':'.$entry->subject_id] ?? ($entry->before['name'] ?? $entry->before['title'] ?? 'deleted')) : null;
                @endphp
                <tr>
                    <td class="whitespace-nowrap align-top">{{ $entry->created_at->timezone($tz)->format('M j, Y') }}<span class="block text-xs text-navy-400">{{ $entry->created_at->timezone($tz)->format('g:i A') }}</span></td>
                    <td class="align-top">@if ($entry->actor){{ $entry->actor->name }}@else<x-admin.pill tone="muted">System</x-admin.pill>@endif<span class="block font-mono text-[11px] text-navy-400">{{ $entry->ip_address }}</span></td>
                    <td class="align-top font-semibold text-navy-950">{{ $entry->action }}</td>
                    <td class="align-top">{{ $record ? ($areas[$entry->subject_type] ?? $entry->subject_type).': '.$record : '—' }}</td>
                    <td class="max-w-md align-top text-navy-600">
                        @if ($entry->reason)<p class="text-sm"><span class="font-semibold text-navy-800">Why:</span> {{ $entry->reason }}</p>@endif
                        @if ($keys)
                            <details class="mt-1 text-xs">
                                <summary class="cursor-pointer font-semibold text-navy-500 hover:text-navy-950">{{ trans_choice('{1} 1 change|[2,*] :count changes', count($keys)) }}</summary>
                                <ul class="mt-2 space-y-1">
                                    @foreach ($keys as $key)
                                        <li class="break-words"><span class="font-mono font-semibold text-navy-800">{{ $key }}</span>: <span class="text-red-700">{{ \Illuminate\Support\Str::limit($show($entry->before[$key] ?? null), 160) }}</span> → <span class="text-emerald-700">{{ \Illuminate\Support\Str::limit($show($entry->after[$key] ?? null), 160) }}</span></li>
                                    @endforeach
                                </ul>
                            </details>
                        @elseif (! $entry->reason)
                            <span class="text-navy-300">—</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="py-12 text-center text-sm text-navy-500">Nothing recorded{{ array_filter($filters) ? ' for these filters' : ' yet' }}.</td></tr>
            @endforelse
        </x-admin.table>
        <x-admin.pagination :paginator="$entries" />
    </x-admin.card>
@endsection
