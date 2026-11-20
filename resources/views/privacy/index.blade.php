<x-layouts.privacy title="Privacy & Data Protection">
    <div class="space-y-5">
        <x-page-header title="Data Privacy Notice" description="Your privacy and the protection of your personal information are important to LIMCOMA Multipurpose Cooperative.">
            @if(!$canAcknowledge || !$notice?->requires_acknowledgement)
                <x-button :href="route('dashboard')" variant="secondary">Back to workspace</x-button>
            @endif
        </x-page-header>
        @if($notice)
            <section class="card" aria-labelledby="current-privacy-title">
                <div class="card-heading">
                    <h2 id="current-privacy-title">{{ $notice->title }}</h2>
                    <x-badge>Version {{ $notice->version }}</x-badge>
                </div>
                <div class="card-body grid justify-items-start gap-3">
                    <p class="text-sm muted">Effective {{ $notice->effective_at->timezone(config('attendance.timezone'))->format('M j, Y g:i A P') }}</p>
                    <p>The full notice explains what employee information LIMCOMA collects, why it is used, how it is protected, and where to ask about your privacy rights.</p>
                    <x-button :href="route('privacy.show', $notice)" variant="secondary">Read Full Privacy Notice</x-button>
                    @if($notice->contact_email)
                        <p><a class="text-link wrap-anywhere" href="mailto:{{ $notice->contact_email }}">Contact the privacy officer</a></p>
                    @endif
                </div>
            </section>
            @if($canAcknowledge)
                <section class="card" aria-labelledby="acknowledgement-title">
                    <div class="card-heading"><h2 id="acknowledgement-title">Acknowledge the notice</h2></div>
                    <form aria-labelledby="acknowledgement-title" method="POST" action="{{ route('privacy.acknowledge') }}" class="card-body grid justify-items-start gap-4">
                        @csrf
                        <input type="hidden" name="notice_id" value="{{ $notice->id }}">
                        <x-form-errors />
                        <p class="text-sm muted">Acknowledgement records receipt and understanding of the notice. Consent for any separate optional processing requires its own choice.</p>
                        <div class="flex items-start gap-3">
                            <input type="checkbox" id="privacy-acknowledged" name="acknowledged" value="1" required class="mt-1 size-5 shrink-0" @error('acknowledged') aria-invalid="true" aria-describedby="privacy-acknowledgement-error" @enderror>
                            <label for="privacy-acknowledged">I acknowledge that I have received and read the LIMCOMA Data Privacy Notice and understand where to find information about how my personal data is processed. <span class="small muted">Required</span></label>
                        </div>
                        @error('acknowledged')<p id="privacy-acknowledgement-error" class="field-error">{{ $message }}</p>@enderror
                        <x-button type="submit">Acknowledge &amp; Continue</x-button>
                    </form>
                </section>
            @endif
        @else
            <section class="card"><div class="card-body"><p>There is currently no published notice available here.</p></div></section>
        @endif

        <section class="card">
            <div class="card-heading"><h2>Your privacy rights</h2></div>
            <div class="card-body space-y-3">
                <p>Philippine privacy law provides rights to information, access, correction, objection, erasure or blocking, damages, complaint, and data portability. The application of each right depends on the processing and applicable legal requirements.</p>
                <p><a class="text-link" href="https://privacy.gov.ph/data-subject-rights/">National Privacy Commission guidance on data subject rights</a></p>
                @if($notice?->contact_email)
                    <p>Contact the designated privacy officer to ask about access, correction, or another request. Use the verified channel to learn what identity verification is needed before sending sensitive documents.</p>
                @endif
            </div>
        </section>

        <section class="card">
            <div class="card-heading"><h2>Your acknowledgement history</h2></div>
            <div class="card-body space-y-3">
                @forelse($acknowledgements as $entry)
                    <p><a class="text-link" href="{{ route('privacy.show', $entry->privacyNotice) }}">Version {{ $entry->privacyNotice->version }}</a> <span class="muted">Acknowledged {{ $entry->acknowledged_at->timezone(config('attendance.timezone'))->format('M j, Y g:i A P') }}</span></p>
                @empty
                    <p class="muted">You have no recorded acknowledgements.</p>
                @endforelse
                @if($acknowledgements instanceof \Illuminate\Contracts\Pagination\Paginator){{ $acknowledgements->links() }}@endif
            </div>
        </section>

        @if($versions->isNotEmpty())
            <section class="card">
                <div class="card-heading"><h2>Published versions</h2></div>
                <div class="card-body space-y-3">
                    @foreach($versions as $version)
                        <p><a class="text-link" href="{{ route('privacy.show', $version) }}">Version {{ $version->version }}</a> <span class="muted">{{ $version->effective_at->timezone(config('attendance.timezone'))->format('M j, Y') }}</span></p>
                    @endforeach
                    {{ $versions->links() }}
                </div>
            </section>
        @endif
    </div>
</x-layouts.privacy>
