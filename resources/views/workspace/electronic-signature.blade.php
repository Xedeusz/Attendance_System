<x-layouts.app title="Electronic Signature">
    <x-page-header title="Electronic Signature" description="Manage the signature you apply when approving leave.">
        <x-button variant="secondary" :href="route('settings')">Account settings</x-button>
    </x-page-header>
    <section class="card max-w-2xl">
        <div class="card-heading">
            <h2>Your signature</h2>
            <x-badge :tone="$signature ? 'green' : 'blue'">{{ $signature ? 'Registered' : 'Not registered' }}</x-badge>
        </div>
        <div class="card-body">
            @if($signature)
                <img class="mb-3 max-h-28 max-w-full object-contain" src="{{ route('supervisor.signature.image') }}" alt="Your registered signature">
                <p class="small muted mb-5">Version {{ $signature->version }} · Registered {{ $signature->registered_at->timezone(config('attendance.timezone'))->format('M j, Y H:i') }}</p>
            @endif
            <form method="POST" enctype="multipart/form-data" action="{{ route('supervisor.signature.store') }}"
                  x-data="{ preview: '' }"
                  @change="if ($event.target.name === 'signature') { if (preview) URL.revokeObjectURL(preview); preview = $event.target.files[0] ? URL.createObjectURL($event.target.files[0]) : '' }">
                @csrf
                <x-input name="signature" label="Signature image" type="file" accept="image/png,image/jpeg" required />
                <p class="small muted mb-4">Upload a PNG or JPEG up to 2 MB. A transparent PNG works well for printing.</p>
                <template x-if="preview">
                    <img class="mb-5 max-h-28 max-w-full object-contain" :src="preview" alt="Selected signature preview">
                </template>
                <x-password name="current_password" label="Confirm your current password" />
                <x-button type="submit">{{ $signature ? 'Replace signature' : 'Save signature' }}</x-button>
            </form>
        </div>
    </section>
</x-layouts.app>
