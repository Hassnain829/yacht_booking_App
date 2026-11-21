{{-- Labelled textarea with validation error. --}}
@props(['name', 'label' => null, 'value' => null, 'rows' => 5, 'hint' => null, 'error' => null, 'required' => false])

@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
    $message = $error ?? $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($message ? $id.'-error' : ''));
@endphp

<div {{ $attributes->only('class')->class('block') }}>
    @if ($label)
        <label for="{{ $id }}" class="text-sm font-bold text-navy-800">{{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif</label>
    @endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" @required($required)
              @if ($message) aria-invalid="true" @endif
              @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
              {{ $attributes->except(['class', 'id'])->class([
                  'mt-2 w-full rounded-xl border bg-white px-4 py-3.5 text-sm text-navy-950 transition focus:outline-hidden focus:ring-4',
                  'border-navy-200 focus:border-navy-950 focus:ring-navy-100' => ! $message,
                  'border-red-500 focus:border-red-600 focus:ring-red-100' => $message,
              ]) }}>{{ old($name, $value) }}</textarea>
    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-navy-500">{{ $hint }}</p>
    @endif
    @if ($message)
        <p id="{{ $id }}-error" class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p>
    @endif
</div>
