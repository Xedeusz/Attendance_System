@props(['record'])
@if($record->exists)<input type="hidden" name="_edit_version" value="{{ old('_edit_version', \App\Services\EditVersion::token($record)) }}">@endif
@error('_edit_version')<div class="p-4" role="alert"><x-alert variant="error">{{ $message }}</x-alert></div>@enderror
