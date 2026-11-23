@extends('layouts.admin', ['title' => $user->name, 'active' => 'users'])

@php
    $tz = config('app.display_timezone');
    $sensitive = $errors->getBag('sensitive');
@endphp

@section('content')
    <x-admin.page-header :title="$user->name" :back="['All users', route('admin.users.index')]" :description="$user->email.' · '.$user->role->label()">
        <x-slot:actions>
            <x-admin.pill :tone="$user->status === \App\Enums\UserStatus::Active ? 'success' : 'danger'">{{ $user->status->label() }}</x-admin.pill>
            @if ($canChange)
                <x-button variant="outline" data-open-modal="user-status" icon-left="shield-alert">Change status</x-button>
                @if ($user->hasEnabledTwoFactorAuthentication())
                    <x-button variant="outline" data-open-modal="user-two-factor" icon-left="key-round">Reset two-factor</x-button>
                @endif
            @endif
        </x-slot:actions>
    </x-admin.page-header>

    @if ($failedRecently >= \App\Http\Controllers\Admin\UserController::FAILED_LOGIN_ALERT)
        <div class="mt-6 flex items-start gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200" role="alert">
            <i data-lucide="triangle-alert" class="mt-0.5 h-4 w-4 shrink-0"></i>
            <p><span class="font-bold">{{ $failedRecently }} failed logins in the last 24 hours.</span> Someone may be guessing this password. Logins are already slowed down; suspend the account if it continues.</p>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-admin.card title="Account">
            <dl class="space-y-3 text-sm">
                <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Phone</dt><dd class="mt-0.5 text-navy-900">{{ $user->phone ?: '—' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Joined</dt><dd class="mt-0.5 text-navy-900">{{ $user->created_at->timezone($tz)->format('M j, Y') }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Email</dt><dd class="mt-0.5 text-navy-900">{{ $user->email_verified_at ? 'Verified' : 'Not verified yet' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Last login</dt><dd class="mt-0.5 text-navy-900">{{ $user->last_login_at?->timezone($tz)->format('M j, Y g:i A') ?? 'Never' }}{{ $user->last_login_ip ? ' · '.$user->last_login_ip : '' }}</dd></div>
                <div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Two-factor login</dt><dd class="mt-0.5 text-navy-900">{{ $user->hasEnabledTwoFactorAuthentication() ? 'On' : 'Off' }}</dd></div>
                @if ($user->isOwner())<div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Boats</dt><dd class="mt-0.5 text-navy-900">{{ $user->boats_count }}</dd></div>@endif
                @if ($user->status_reason)<div><dt class="text-xs font-bold uppercase tracking-wider text-navy-400">Status reason</dt><dd class="mt-0.5 text-navy-900">{{ $user->status_reason }}</dd></div>@endif
            </dl>
            <p class="mt-5 text-xs text-navy-500">Bookings, payments and earnings appear here from Phase 4.</p>
        </x-admin.card>

        <x-admin.card title="Login history" flush class="lg:col-span-2">
            @if ($events->isEmpty())
                <p class="p-5 text-sm text-navy-500">No logins yet.</p>
            @else
                <x-admin.table min="min-w-[36rem]">
                    <x-slot:head><th scope="col">When</th><th scope="col">What</th><th scope="col">IP</th><th scope="col">Device</th></x-slot:head>
                    @foreach ($events as $event)
                        <tr>
                            <td class="whitespace-nowrap">{{ $event->created_at->timezone($tz)->format('M j, g:i A') }}</td>
                            <td><x-admin.pill :tone="in_array($event->event->value, ['failed', 'lockout', '2fa_failed'], true) ? 'danger' : 'muted'">{{ $event->event->label() }}</x-admin.pill></td>
                            <td class="font-mono text-xs">{{ $event->ip_address ?? '—' }}</td>
                            <td class="text-xs text-navy-500">{{ $event->user_agent ? \App\Support\UserAgent::describe($event->user_agent) : '—' }}</td>
                        </tr>
                    @endforeach
                </x-admin.table>
            @endif
        </x-admin.card>
    </div>

    <x-admin.card title="Audit log for this account" flush class="mt-6">
        @if ($audit->isEmpty())
            <p class="p-5 text-sm text-navy-500">Nothing recorded yet.</p>
        @else
            <x-admin.table min="min-w-[36rem]">
                <x-slot:head><th scope="col">When</th><th scope="col">Who</th><th scope="col">Action</th><th scope="col">Reason</th></x-slot:head>
                @foreach ($audit as $entry)
                    <tr>
                        <td class="whitespace-nowrap">{{ $entry->created_at->timezone($tz)->format('M j, Y g:i A') }}</td>
                        <td>{{ $entry->actor?->name ?? 'System' }}</td>
                        <td class="font-semibold text-navy-950">{{ $entry->action }}</td>
                        <td class="text-navy-600">{{ $entry->reason ?? '—' }}</td>
                    </tr>
                @endforeach
            </x-admin.table>
        @endif
    </x-admin.card>

    @if ($canChange)
        @push('modals')
            <x-admin.modal id="user-status" title="Change {{ $user->name }}'s status" :open="old('dialog') === 'user-status' && $sensitive->any()">
                <x-admin.sensitive-form id="user-status" :action="route('admin.users.status', $user)" submit="Change status" danger>
                    <x-form.select name="status" id="user-status-select" label="New status" :options="$statuses" required :error="old('dialog') === 'user-status' ? $sensitive->first('status') : null" hint="Suspended or banned accounts are logged out everywhere and can't log in." />
                </x-admin.sensitive-form>
            </x-admin.modal>
            @if ($user->hasEnabledTwoFactorAuthentication())
                <x-admin.modal id="user-two-factor" title="Reset two-factor login" :open="old('dialog') === 'user-two-factor' && $sensitive->any()">
                    <x-admin.sensitive-form id="user-two-factor" :action="route('admin.users.two-factor', $user)" submit="Turn off two-factor login" danger>
                        <p class="rounded-xl bg-amber-50 p-3 text-sm leading-relaxed text-amber-900 ring-1 ring-amber-200">Only after you have checked it's really them (e.g. a call). They are logged out everywhere, get an email, and can turn it on again after logging in.</p>
                    </x-admin.sensitive-form>
                </x-admin.modal>
            @endif
        @endpush
    @endif
@endsection
