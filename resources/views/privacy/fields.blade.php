<x-form-errors />
<div class="form-grid">
    <x-input name="version" label="Version" :value="$notice?->version" maxlength="40" required />
    <x-input name="title" label="Title" :value="$notice?->title" maxlength="150" required />
    <x-input name="effective_at" :label="'Effective date and time ('.config('app.timezone').')'" type="datetime-local" :value="$notice?->effective_at?->format('Y-m-d\TH:i')" required />
    <x-input name="contact_email" label="Verified privacy contact email" type="email" :value="$notice?->contact_email" maxlength="254" />
</div>
<div class="form-group">
    <label class="field-label" for="privacy-content">Notice text (required)</label>
    <textarea class="field min-h-64!" id="privacy-content" name="content" rows="14" maxlength="50000" required aria-describedby="privacy-text-help">{{ old('content', $notice?->content) }}</textarea>
    <p class="small muted mt-2" id="privacy-text-help">Use plain text. Start each section heading with ## and leave a blank line between sections. Confirm the processing purposes, lawful bases, recipients, retention and contact before publication.</p>
</div>
<label class="mb-4 flex items-start gap-3">
    <input type="checkbox" name="requires_acknowledgement" value="1" class="mt-1 size-5 shrink-0" @checked(old('requires_acknowledgement', $notice?->requires_acknowledgement))>
    <span>Require acknowledgement before workspace access when this version becomes effective. Select this only if the approval covers mandatory acknowledgement.</span>
</label>
