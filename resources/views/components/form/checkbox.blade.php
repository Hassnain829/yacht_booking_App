{{--
    Checkbox with label text in the slot. <x-form.checkbox name="agree" required>I agree to the <a …>terms</a></x-form.checkbox>

    `hidden-zero`: also posts name=0 when unticked. Use it on a page with several forms: then only this form's
    old input decides the tick, and a failed submit of another form shows the saved value instead of "unticked".
--}}
@props(['name', 'value' => '1', 'checked' => false, 'error' => null, 'required' => false, 'hiddenZero' => false])

@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
    $message = $error ?? $errors->first($name);
    $isChecked = $hiddenZero
        ? (old($name) !== null ? (string) old($name) === (string) $value : $checked)
        : (old() ? (string) old($name) === (string) $value : $checked);
@endphp

<div {{ $attributes->only('class') }}>
    @if ($hiddenZero)
        <input type="hidden" name="{{ $name }}" value="0">
    @endif
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
