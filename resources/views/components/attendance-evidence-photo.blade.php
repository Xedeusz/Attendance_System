@props(['entry', 'title'])
<section {{ $attributes->class(['card min-w-0 attendance-photo-card']) }} aria-label="{{ $title }} photo evidence">
<div class="card-heading"><h2>{{ $title }}</h2>@if($entry['event'])<x-state :value="$entry['event']->verification_status" />@else<x-badge>Unavailable</x-badge>@endif</div>
<div class="card-body space-y-3">
@if($entry['event'])
<p class="small font-semibold">{{ $entry['evidence']['map']['recordedAt'] }}</p>
@if($entry['event']->photo && !$entry['event']->photo->purged_at)<img class="attendance-video attendance-comparison-photo" src="{{ route('attendance.photo', $entry['event']->photo) }}" alt="Submitted {{ strtolower($title) }} office photo" loading="lazy">@else<x-badge>Missing Photo</x-badge><p class="small muted">Photo is no longer available under the retention policy.</p>@endif
@else<p class="small muted attendance-photo-unavailable">No {{ $title }} submission recorded.</p>@endif
</div></section>
