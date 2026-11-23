{{--
    Form for a sensitive admin action (PLAN §8.2 rule 15): a written reason, the password, an audit log entry.
    <x-admin.sensitive-form id="trash-harbor" :action="route('admin.harbors.trash', $harbor)" method="DELETE" submit="Move to Trash" danger> …extra fields… </x-admin.sensitive-form>

    - With a real `action` it posts to that route (SensitiveActionRequest). The password field is shown only when
      it wasn't confirmed in the last 3 hours (the same window as `password.confirm`).
    - Errors come back in the "sensitive" bag; `id` is sent as `dialog`, so only this form shows them and its
      dialog reopens (x-admin.modal :open).
    - Without an action (screens still on demo data) it stays a Phase 1 demo form that shows a toast.
--}}
@props(['id', 'action' => '#', 'method' => 'POST', 'submit' => 'Confirm', 'danger' => false, 'message' => 'Demo: this admin action goes live with the database (Phase 3+).'])

@php
    $demo = $action === '#';
    $bag = $errors->getBag('sensitive');
    $mine = old('dialog') === $id;
    $needsPassword = ! $demo && \App\Http\Requests\Admin\SensitiveActionRequest::needsPassword();
@endphp

<form method="POST" action="{{ $action }}" @if ($demo) data-demo-form="{{ $message }}" @endif {{ $attributes->class('space-y-4') }}>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    @unless ($demo)
        <input type="hidden" name="dialog" value="{{ $id }}">
    @endunless
    @if ($mine && $bag->has('action'))
        <div class="space-y-1 rounded-xl bg-red-50 p-3 text-sm font-semibold text-red-700 ring-1 ring-red-200" role="alert">
            @foreach ($bag->get('action') as $problem)
                <p class="flex items-start gap-2"><i data-lucide="circle-alert" class="mt-0.5 h-4 w-4 shrink-0"></i>{{ $problem }}</p>
            @endforeach
        </div>
    @endif
    {{ $slot }}
    <x-form.textarea name="reason" :id="$id.'-reason'" label="Reason (saved in the audit log)" rows="3" required minlength="10" maxlength="500" placeholder="Why are you doing this?" :error="$mine ? $bag->first('reason') : null" />
    @if ($needsPassword)
        <x-form.password name="password" :id="$id.'-password'" label="Your password" autocomplete="current-password" maxlength="128" :error="$mine ? $bag->first('password') : null" hint="Asked at most once every 3 hours." />
    @endif
    <p class="flex items-start gap-2 rounded-xl bg-navy-50 p-3 text-xs leading-relaxed text-navy-600"><i data-lucide="lock" class="h-4 w-4 shrink-0 text-brass-500"></i>@if ($demo)You'll be asked for your password, and this action is recorded in the audit log.@elseif ($needsPassword)Your password confirms it's you. This action is recorded in the audit log.@else You confirmed your password recently, so it isn't asked again. This action is recorded in the audit log.@endif</p>
    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <x-button type="button" variant="outline" data-close-modal>Cancel</x-button>
        <button type="submit" @class(['inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-bold text-white transition', 'bg-red-600 hover:bg-red-700' => $danger, 'bg-navy-950 hover:bg-navy-800' => ! $danger])>{{ $submit }}</button>
    </div>
</form>
