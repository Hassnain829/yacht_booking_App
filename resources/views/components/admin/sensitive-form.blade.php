{{--
    Form for a sensitive admin action (PLAN §8.2 rule 6): a written reason, password confirmation, audit log entry.
    <x-admin.sensitive-form id="refund" submit="Refund $120" danger> …extra fields… </x-admin.sensitive-form>
    PHASE 1: data-demo-form (shows a toast). Phase 2+: posts to the real route behind `password.confirm`.
--}}
@props(['id', 'action' => '#', 'method' => 'POST', 'submit' => 'Confirm', 'danger' => false, 'message' => 'Demo: this admin action goes live with the database (Phase 3+).'])

<form method="POST" action="{{ $action }}" data-demo-form="{{ $message }}" {{ $attributes->class('space-y-4') }}>
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif
    {{ $slot }}
    <x-form.textarea name="reason" :id="$id.'-reason'" label="Reason (saved in the audit log)" rows="3" required minlength="10" maxlength="500" placeholder="Why are you doing this?" />
    <p class="flex items-start gap-2 rounded-xl bg-navy-50 p-3 text-xs leading-relaxed text-navy-600"><i data-lucide="lock" class="h-4 w-4 shrink-0 text-brass-500"></i>You'll be asked for your password, and this action is recorded in the audit log.</p>
    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <x-button type="button" variant="outline" data-close-modal>Cancel</x-button>
        <button type="submit" @class(['inline-flex items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-bold text-white transition', 'bg-red-600 hover:bg-red-700' => $danger, 'bg-navy-950 hover:bg-navy-800' => ! $danger])>{{ $submit }}</button>
    </div>
</form>
