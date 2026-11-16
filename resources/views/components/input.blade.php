@props(['name', 'label', 'type' => 'text', 'value' => null])
@php($descriptionIds = trim(($attributes->get('aria-describedby') ?? '').' '.($errors->has($name) ? $name.'-error' : '')))
<div class="form-group">
    <div class="field-label-row"><label class="field-label" for="{{ $name }}">{{ $label }}</label>@if($attributes->has('required'))<span class="field-required" aria-hidden="true">Required</span>@endif</div>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $type === 'password' ? '' : old($name, $value) }}" {{ $attributes->except('aria-describedby')->class(['field']) }} @if($descriptionIds !== '') aria-describedby="{{ $descriptionIds }}" @endif @error($name) aria-invalid="true" @enderror>
    @error($name)<p class="field-error" id="{{ $name }}-error">{{ $message }}</p>@enderror
</div>
