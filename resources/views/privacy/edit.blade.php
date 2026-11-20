<x-layouts.app title="Privacy Notice Version">
    <x-page-header :title="'Privacy Notice '.$notice->version" :description="$notice->published_at ? 'Published version' : 'Draft version'">
        <x-button :href="route('hr.privacy.index')" variant="secondary">Back to notice management</x-button>
    </x-page-header>
    <div class="space-y-5">
        @if(session('status'))<x-alert>{{ session('status') }}</x-alert>@endif
        <section class="card">
            <div class="card-heading"><h2>Notice preview</h2></div>
            <div class="card-body space-y-3">
                <h3>{{ $notice->title }}</h3>
                <p class="small muted">Effective {{ $notice->effective_at->timezone(config('attendance.timezone'))->format('M j, Y g:i A P') }} · {{ $notice->requires_acknowledgement ? 'Acknowledgement required' : 'Acknowledgement optional' }}</p>
                @include('privacy.content')
                @if($notice->contact_email)<p class="wrap-anywhere">Privacy contact {{ $notice->contact_email }}</p>@endif
            </div>
        </section>
        @if(!$notice->published_at)
            <section class="card">
                <div class="card-heading"><h2 id="edit-draft">Edit draft</h2></div>
                <form aria-labelledby="edit-draft" class="card-body" method="POST" action="{{ route('hr.privacy.update', $notice) }}">
                    @csrf
                    @method('PATCH')
                    @include('privacy.fields')
                    <x-button type="submit">Save changes</x-button>
                </form>
            </section>
            <section class="card">
                <div class="card-heading"><h2 id="publish-notice">Publish approved notice</h2></div>
                <form aria-labelledby="publish-notice" class="card-body" method="POST" action="{{ route('hr.privacy.publish', $notice) }}">
                    @csrf
                    <input type="hidden" name="revision" value="{{ $notice->draftFingerprint() }}">
                    <x-input name="approval_reference" label="DPO or legal approval reference" maxlength="150" required />
                    <label class="mb-4 flex items-start gap-3"><input type="checkbox" name="approved" value="1" class="mt-1 size-5 shrink-0" required> I confirm that the authorized reviewer approved this version and its acknowledgement policy for publication.</label>
                    <x-button type="submit">Publish notice</x-button>
                </form>
            </section>
        @elseif(!$notice->archived_at && $notice->effective_at->lte(now()) && ! $notice->is($current))
            <section class="card">
                <div class="card-heading"><h2>Archive superseded version</h2></div>
                <form class="card-body" method="POST" action="{{ route('hr.privacy.archive', $notice) }}">
                    @csrf
                    <x-button type="submit" variant="secondary">Archive version</x-button>
                </form>
            </section>
        @endif
    </div>
</x-layouts.app>
