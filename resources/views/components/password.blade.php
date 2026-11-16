@props(['name' => 'password', 'label' => 'Password', 'autocomplete' => 'current-password'])
<div class="form-group" x-data="{ visible: false }">
    <div class="field-label-row"><label class="field-label" for="{{ $name }}">{{ $label }}</label><span class="field-required" aria-hidden="true">Required</span></div>
    <div class="password-wrap"><input id="{{ $name }}" name="{{ $name }}" type="password" :type="visible ? 'text' : 'password'" autocomplete="{{ $autocomplete }}" class="field" required @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror><button type="button" class="icon-button" @click="visible = !visible" :aria-label="visible ? 'Hide {{ $label }}' : 'Show {{ $label }}'" :aria-pressed="visible" aria-label="Show {{ $label }}"><x-icon name="eye" /></button></div>
    @error($name)<p class="field-error" id="{{ $name }}-error">{{ $message }}</p>@enderror
</div>
