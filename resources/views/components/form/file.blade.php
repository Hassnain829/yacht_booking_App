{{--
    Image / document upload field. The server checks the real file (SafeUpload); `accept` only helps the picker.
    <x-form.file name="image" label="Photo" :preview="$harbor->imageUrl()" hint="JPG, PNG or WEBP, at least 600 × 400 px." remove-label="Remove the photo" />
--}}
@props(['name', 'label' => null, 'hint' => null, 'error' => null, 'preview' => null, 'previewAlt' => '', 'removeLabel' => null, 'accept' => 'image/jpeg,image/png,image/webp,image/gif'])

@php
    $id = $attributes->get('id', 'f-'.str_replace(['[', ']', '.', '_'], '-', $name));
    $message = $error ?? $errors->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($message ? $id.'-error' : ''));
@endphp

<div {{ $attributes->only('class')->class('block') }}>
    @if ($label)
        <label for="{{ $id }}" class="text-sm font-bold text-navy-800">{{ $label }}</label>
    @endif
    @if ($preview)
        <img src="{{ $preview }}" alt="{{ $previewAlt }}" class="mt-2 aspect-[3/2] w-full rounded-xl object-cover ring-1 ring-navy-100">
    @endif
    <input id="{{ $id }}" name="{{ $name }}" type="file" accept="{{ $accept }}"
           @if ($message) aria-invalid="true" @endif
           @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
           {{ $attributes->except(['class', 'id'])->class([
               'mt-2 block w-full rounded-xl border bg-white text-sm text-navy-700 file:mr-4 file:cursor-pointer file:rounded-l-xl file:border-0 file:bg-navy-950 file:px-4 file:py-3 file:text-sm file:font-bold file:text-white hover:file:bg-navy-800 focus:outline-hidden focus:ring-4',
               'border-navy-200 focus:ring-navy-100' => ! $message,
               'border-red-500 focus:ring-red-100' => $message,
           ]) }}>
    @if ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-xs text-navy-500">{{ $hint }}</p>
    @endif
    @if ($message)
        <p data-field-error id="{{ $id }}-error" class="mt-1.5 text-xs font-semibold text-red-600">{{ $message }}</p>
    @endif
    @if ($preview && $removeLabel)
        <x-form.checkbox :name="'remove_'.$name" :id="$id.'-remove'" class="mt-3">{{ $removeLabel }}</x-form.checkbox>
    @endif
</div>
