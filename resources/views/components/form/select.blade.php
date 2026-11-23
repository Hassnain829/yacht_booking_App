{{--
    Labelled select. `options` = [value => label]. `placeholder` adds an empty first option.

    <x-form.select name="experience" label="Interested in" :options="$experienceOptions" placeholder="Not sure yet" />
--}}
@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'error' => null, 'required' => false])

@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.'], '-', $name));
    $message = $error ?? $errors->first($name);
    $selected = (string) old($name, $value);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($message ? $id.'-error' : ''));
@endphp

<div {{ $attributes->only('class')->class('block') }}>
    @if ($label)
        <label for="{{ $id }}" class="text-sm font-bold text-navy-800">{{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif</label>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" @required($required)
            @if ($message) aria-invalid="true" @endif
            @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
            {{ $attributes->except(['class', 'id'])->class([
                'mt-2 w-full rounded-xl border bg-white px-4 py-3.5 text-sm text-navy-950 transition focus:outline-hidden focus:ring-4',
                'border-navy-200 focus:border-navy-950 focus:ring-navy-100' => ! $message,
                'border-red-500 focus:border-red-600 focus:ring-red-100' => $message,
            ]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-navy-500">{{ $hint }}</p>
    @endif
    @if ($message)
        <p data-field-error id="{{ $id }}-error" class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p>
    @endif
</div>
