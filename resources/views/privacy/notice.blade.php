<x-layouts.privacy :title="$notice->title">
    <x-page-header :title="$notice->title" description="LIMCOMA Multipurpose Cooperative">
        <x-button :href="route('privacy.index')" variant="secondary">Back to Privacy &amp; Data Protection</x-button>
    </x-page-header>
    <article class="card">
        <div class="card-heading">
            <h2>Version {{ $notice->version }}</h2>
            <span class="small muted">Effective {{ $notice->effective_at->timezone(config('attendance.timezone'))->format('M j, Y g:i A P') }}</span>
        </div>
        <div class="card-body space-y-5">
            @include('privacy.content')
            @if($notice->contact_email)
                <a class="text-link wrap-anywhere" href="mailto:{{ $notice->contact_email }}">Contact the privacy officer</a>
            @endif
        </div>
    </article>
</x-layouts.privacy>
