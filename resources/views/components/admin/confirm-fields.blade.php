{{--
    Reason + password at the bottom of a full-page sensitive form (Settings, cancellation policies — PLAN §8.2
    rule 15). The request extends SensitiveActionRequest with the default error bag. The password is skipped
    within 3 hours of the last confirmation, like `password.confirm`.
--}}
@props(['placeholder' => 'Why are you changing this?'])

@php($needsPassword = \App\Http\Requests\Admin\SensitiveActionRequest::needsPassword())

<div {{ $attributes->class('grid gap-5 md:grid-cols-2') }}>
    <x-form.textarea name="reason" label="Reason (saved in the audit log)" rows="2" required minlength="10" maxlength="500" :placeholder="$placeholder" :class="$needsPassword ? '' : 'md:col-span-2'" />
    @if ($needsPassword)
        <x-form.password name="password" label="Your password" autocomplete="current-password" maxlength="128" hint="Asked at most once every 3 hours." />
    @endif
    <p class="flex items-start gap-2 rounded-xl bg-navy-50 p-3 text-xs leading-relaxed text-navy-600 md:col-span-2"><i data-lucide="lock" class="h-4 w-4 shrink-0 text-brass-500"></i>{{ $needsPassword ? 'Your password confirms it\'s you.' : 'You confirmed your password recently, so it isn\'t asked again.' }} The change is recorded in the audit log.</p>
</div>
