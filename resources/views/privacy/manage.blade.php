<x-layouts.app title="Privacy Notice Management">
    <x-page-header title="Privacy Notice Management" description="Prepare draft versions and publish approved notices." />
    <section class="card mb-5">
        <div class="card-heading"><h2>Notice versions</h2></div>
        <div class="card-body">
            <ul class="divide-y divide-(--border-color)">
                @forelse($notices as $notice)
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 py-3">
                        <a class="text-link wrap-anywhere" href="{{ route('hr.privacy.show', $notice) }}">{{ $notice->version }} · {{ $notice->title }}</a>
                        <x-badge>{{ $notice->is($current) ? 'Current' : ($notice->archived_at ? 'Archived' : ($notice->published_at ? 'Published' : 'Draft')) }}</x-badge>
                        <span class="small muted">{{ $notice->acknowledgements_count }} acknowledgements</span>
                    </li>
                @empty
                    <li class="muted">No notice versions have been created.</li>
                @endforelse
            </ul>
            {{ $notices->links() }}
        </div>
    </section>
    <section class="card">
        <div class="card-heading"><h2 id="create-privacy-draft">Create a draft</h2></div>
        <form aria-labelledby="create-privacy-draft" class="card-body" method="POST" action="{{ route('hr.privacy.store') }}">
            @csrf
            @include('privacy.fields', ['notice' => null])
            <x-button type="submit">Save draft</x-button>
        </form>
    </section>
</x-layouts.app>
