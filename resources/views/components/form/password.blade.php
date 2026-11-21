{{--
    Password input with a show / hide button (ui/password-toggle.js).
    <x-form.password name="password" label="Password" autocomplete="current-password">
        <x-slot:aside><a href="…">Forgot password?</a></x-slot:aside>
    </x-form.password>
--}}
@props(['name', 'label' => 'Password', 'hint' => null, 'error' => null, 'required' => true, 'aside' => null])
@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.', '_'], '-', $name));
    $message = $error ?? $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($message ? $id.'-error' : ''));
@endphp
<div {{ $attributes->only('class')->class('block') }}>
    <div class="flex items-center justify-between gap-3">
        <label for="{{ $id }}" class="text-sm font-bold text-navy-800">{{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif</label>
        {{ $aside }}
    </div>
    <div class="relative mt-2">
        <input id="{{ $id }}" name="{{ $name }}" type="password" @required($required)
               @if ($message) aria-invalid="true" @endif
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               {{ $attributes->except(['class', 'id'])->class([
                   'w-full rounded-xl border bg-white py-3.5 pl-4 pr-12 text-sm text-navy-950 transition placeholder:text-navy-300 focus:outline-hidden focus:ring-4',
                   'border-navy-200 focus:border-navy-950 focus:ring-navy-100' => ! $message,
                   'border-red-500 focus:border-red-600 focus:ring-red-100' => $message,
               ]) }}>
        <button type="button" data-toggle-password aria-controls="{{ $id }}" aria-pressed="false" aria-label="Show password"
                class="absolute right-2 top-1/2 grid h-9 w-9 -translate-y-1/2 place-items-center rounded-lg text-navy-400 transition hover:bg-navy-50 hover:text-navy-700">
            <i data-lucide="eye" class="h-4 w-4" data-icon-show></i>
            <i data-lucide="eye-off" class="hidden h-4 w-4" data-icon-hide></i>
        </button>
    </div>
    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-navy-500">{{ $hint }}</p>
    @endif
    @if ($message)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p>
    @endif
</div>
