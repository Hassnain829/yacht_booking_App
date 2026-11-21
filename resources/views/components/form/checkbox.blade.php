{{-- Checkbox with label text in the slot. <x-form.checkbox name="agree" required>I agree to the <a …>terms</a></x-form.checkbox> --}}
@props(['name', 'value' => '1', 'checked' => false, 'error' => null, 'required' => false])

@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
    $message = $error ?? $errors->first($name);
    $isChecked = old() ? (string) old($name) === (string) $value : $checked;
@endphp

<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="flex cursor-pointer items-start gap-3 text-sm text-navy-700">
        <input id="{{ $id }}" name="{{ $name }}" type="checkbox" value="{{ $value }}" @checked($isChecked) @required($required)
               @if ($message) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
               {{ $attributes->except(['class', 'id'])->class('mt-0.5 h-5 w-5 shrink-0 rounded border-navy-300 accent-navy-950 focus:ring-4 focus:ring-navy-100') }}>
        <span>{{ $slot }}</span>
    </label>
    @if ($message)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p>
    @endif
</div>
