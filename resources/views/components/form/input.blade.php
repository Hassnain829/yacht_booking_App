{{--
    Labelled text input with validation error (mockup contact form style).

    <x-form.input name="email" type="email" label="Email" required autocomplete="email" placeholder="jane@email.com" />

    - The value is old() input, falling back to `value`.
    - Errors come from Laravel's $errors bag (FormRequest), or the `error` prop.
    - `hint` shows helper text under the field. `icon` shows a leading Lucide icon.
--}}
@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'error' => null, 'icon' => null, 'required' => false])

@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
    $message = $error ?? $errors->first(str_replace(['[', ']'], ['.', ''], $name));
    $describedBy = trim(($hint ? $id.'-hint ' : '').($message ? $id.'-error' : ''));
@endphp

<div {{ $attributes->only('class')->class('block') }}>
    @if ($label)
        <label for="{{ $id }}" class="text-sm font-bold text-navy-800">{{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif</label>
    @endif
    <div class="relative mt-2">
        @if ($icon)
            <i data-lucide="{{ $icon }}" class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-navy-400"></i>
        @endif
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
               @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
               @required($required)
               @if ($message) aria-invalid="true" @endif
               @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
               {{ $attributes->except(['class', 'id'])->class([
                   'w-full rounded-xl border bg-white py-3.5 pr-4 text-sm text-navy-950 transition focus:outline-hidden focus:ring-4',
                   'pl-11' => $icon,
                   'pl-4' => ! $icon,
                   'border-navy-200 focus:border-navy-950 focus:ring-navy-100' => ! $message,
                   'border-red-500 focus:border-red-600 focus:ring-red-100' => $message,
               ]) }}>
    </div>
    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-navy-500">{{ $hint }}</p>
    @endif
    @if ($message)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p>
    @endif
</div>
