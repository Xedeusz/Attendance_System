@props(['name', 'label', 'options', 'value' => null, 'placeholder' => 'Choose an option', 'id' => null])
@php($fieldId = $id ?? $name)
<div class="form-group">
    <label class="field-label" for="{{ $fieldId }}">{{ $label }}</label>
    <select id="{{ $fieldId }}" name="{{ $name }}" {{ $attributes->class(['field']) }} @error($name) aria-invalid="true" aria-describedby="{{ $fieldId }}-error" @enderror>
        @if($placeholder)<option value="">{{ $placeholder }}</option>@endif
        @foreach($options as $key => $text)<option value="{{ $key }}" @selected((string) old($name, $value) === (string) $key)>{{ $text }}</option>@endforeach
    </select>
    @error($name)<p class="field-error" id="{{ $fieldId }}-error">{{ $message }}</p>@enderror
</div>
