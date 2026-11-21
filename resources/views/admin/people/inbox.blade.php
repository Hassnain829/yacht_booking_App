@extends('layouts.admin', ['title' => 'Inbox', 'active' => 'inbox'])

@section('content')
    <x-admin.page-header title="Inbox" description="Messages from the contact form. Replies are sent from the business email address." />

    <x-admin.card flush class="mt-6">
        <ul class="divide-y divide-navy-100">
            @foreach ($messages as $m)
                <li @class(['flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center', 'bg-brass-50/40' => $m->status === 'new'])>
                    <span @class(['grid h-10 w-10 shrink-0 place-items-center rounded-full text-sm font-bold', 'bg-navy-950 text-brass-300' => $m->status !== 'spam', 'bg-navy-100 text-navy-400' => $m->status === 'spam'])>{{ strtoupper(substr($m->name, 0, 1)) }}</span>
                    <div class="min-w-0 flex-1">
                        <p class="flex flex-wrap items-center gap-2"><span @class(['text-sm text-navy-950', 'font-extrabold' => $m->status === 'new', 'font-semibold' => $m->status !== 'new'])>{{ $m->subject }}</span></p>
                        <p class="mt-0.5 truncate text-xs text-navy-500">{{ $m->name }} · {{ $m->email }}@if ($m->experience) · {{ $m->experience }}@endif @if ($m->guests) · {{ $m->guests }} guests @endif</p>
                    </div>
                    <div class="flex items-center gap-3 sm:text-right">
                        <x-admin.pill :tone="match ($m->status) { 'new' => 'warning', 'replied' => 'success', 'spam' => 'muted', default => 'navy' }">{{ ucfirst($m->status) }}</x-admin.pill>
                        <span class="whitespace-nowrap text-xs text-navy-400">{{ $m->received_at->diffForHumans() }}</span>
                        <x-button variant="outline" size="sm" data-soon>Open</x-button>
                    </div>
                </li>
            @endforeach
        </ul>
    </x-admin.card>
@endsection
