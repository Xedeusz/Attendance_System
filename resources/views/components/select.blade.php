@props(['name', 'label', 'options', 'value' => null, 'placeholder' => 'Choose an option', 'id' => null])
@php($fieldId = $id ?? $name)
@php($descriptionIds = trim(($attributes->get('aria-describedby') ?? '').' '.($errors->has($name) ? $fieldId.'-error' : '')))
<div class="form-group">
    <div class="field-label-row"><label class="field-label" for="{{ $fieldId }}">{{ $label }}</label>@if($attributes->has('required'))<span class="field-required" aria-hidden="true">Required</span>@endif</div>
    <select id="{{ $fieldId }}" name="{{ $name }}" {{ $attributes->except('aria-describedby')->class(['field']) }} @if($descriptionIds !== '') aria-describedby="{{ $descriptionIds }}" @endif @error($name) aria-invalid="true" @enderror>
        @if($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $key => $text)<option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>@endforeach
    </select>
    @error($name)<p class="field-error" id="{{ $fieldId }}-error">{{ $message }}</p>@enderror
</div>
