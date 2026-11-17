@props(['day'])
<section {{ $attributes->class(['card attendance-location min-w-0']) }} x-data="attendanceLocationMap" data-map-config="{{ json_encode($day['map']) }}" aria-label="Shared Attendance Location">
<div class="card-heading"><h2>Attendance Location</h2><span class="small muted">Shared daily evidence map</span></div>
<div class="card-body space-y-4">
<p class="small muted">Recorded branch, Time In and Time Out locations for this attendance record.</p>
@if(!$day['map']['branch'])<p class="small muted">Historical branch coordinates are unavailable. The current branch configuration has not been substituted.</p>@endif
@if($day['map']['branch'] || collect($day['map']['events'])->contains(fn($event) => $event['employee'] !== null))
<div x-ref="map" class="attendance-evidence-map" role="region" tabindex="0" aria-label="Shared recorded attendance location map"></div>
<p class="small muted" role="status" x-show="!ready && !mapError">Loading recorded location map...</p>
<div class="flex flex-wrap items-center gap-3 small" aria-label="Map legend">
@foreach(['branch' => 'Recorded branch', 'time_in' => 'Time In', 'time_out' => 'Time Out'] as $type => $label)
<button type="button" class="attendance-map-legend-button" x-on:click="openEvidence('{{ $type }}')" x-bind:disabled="!hasMarker('{{ $type }}')" aria-label="Show {{ $label }} location details"><span class="attendance-map-key {{ $type === 'branch' ? 'branch' : ($type === 'time_in' ? 'employee' : 'time-out') }}" aria-hidden="true"></span>{{ $label }}</button>
@endforeach
<x-button variant="secondary" type="button" x-on:click="fitEvidence()" x-bind:disabled="!ready">Fit recorded locations</x-button></div>
<p class="small muted">Select a pin or its label to view details, including when locations overlap.</p>
@else<p class="small muted">Attendance location data is unavailable.</p>@endif
<p class="small text-amber-800" role="status" x-show="mapError" x-text="mapError" x-cloak></p>
<noscript><p class="small muted">JavaScript is required for the interactive map. Recorded location information is available below.</p></noscript>
<p class="small muted leading-6">Coordinates were provided by the employee's device and evaluated against each event's recorded branch geofence. The map visualizes submitted evidence; it does not independently verify physical presence.</p>
</div></section>
