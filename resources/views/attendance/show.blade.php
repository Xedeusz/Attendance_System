<x-layouts.app title="Attendance Details"><x-page-header :title="'Attendance · '.$record->work_date->format('Y-m-d')" :description="$record->employee->full_name.' | '.$record->employee->employee_number.' · '.$record->branch->branch_name"><x-button variant="secondary" :href="$hr ? route('hr.attendance.index') : route('employee.attendance.history')">Back to records</x-button></x-page-header>
<x-attendance-identity :employee="$record->employee" :branch-name="$record->branch->branch_name" class="mb-6" />
@if($record->employee->user_id===auth()->id())<div class="action-row mb-6"><x-button variant="secondary" :href="route('employee.corrections.create',['record_id'=>$record->id])">Request correction</x-button></div>@endif
@if($record->correction_version)<section class="card mb-6"><div class="card-heading"><h2>Effective attendance · manual HR correction</h2></div><div class="card-body"><p>Time In: {{ ($record->effective_time_in_at ?? $record->time_in_at)?->timezone(config('attendance.timezone'))->format('M j, Y H:i') ?? 'Not recorded' }}<br>Time Out: {{ ($record->effective_time_out_at ?? $record->time_out_at)?->timezone(config('attendance.timezone'))->format('M j, Y H:i') ?? 'Not recorded' }}</p><p class="small muted mt-3">The original timestamps, photos and GPS results below remain unchanged. Manual correction does not verify location or camera evidence.</p><a class="text-link mt-3" href="{{ route($hr ? 'hr.corrections.index' : 'employee.corrections.index') }}">Correction history</a></div></section>@endif
<section class="card mb-6"><div class="card-body flex flex-wrap gap-4"><x-state :value="$record->session_status" /><x-state :value="$record->verification_status" /><x-state :value="$record->review_status" /><p class="small muted">Time In and Time Out are displayed in {{ config('attendance.timezone') }}.</p></div></section>

<section class="card mb-6">
    <div class="card-heading"><h2>Working hours breakdown</h2></div>
    <div class="card-body space-y-3">
        <x-attendance-computation :calculation="$record->computation" />
        @if($record->computation)<p class="small muted">Durations are rounded down to whole minutes. A dash indicates an unavailable or inapplicable value.</p>@endif
    </div>
    @if($hr && $versions->isNotEmpty())
        <details class="card-body">
            <summary>Computation history</summary>
            <div class="mt-4 stack">
                @foreach($versions as $version)
                    <div class="border-t border-slate-200 pt-3">
                        <p class="small mb-2">Version {{ $version->version }} · {{ $version->created_at->timezone(config('attendance.timezone'))->format('M j, Y H:i') }} · {{ str_replace('_', ' ', $version->trigger) }}@if($version->actor_id) · Account #{{ $version->actor_id }}@endif</p>
                        @if($version->reason)<p class="small mb-2">{{ $version->reason }}</p>@endif
                        <x-attendance-computation :calculation="$version" />
                    </div>
                @endforeach
            </div>
        </details>
    @endif
</section>

@php($day = app(\App\Services\AttendanceLocationEvidence::class)->day($record))
<div class="stack" data-attendance-record>
<div class="attendance-photo-comparison">@foreach($day['entries'] as $type => $entry)<x-attendance-evidence-photo :entry="$entry" :title="ucwords(str_replace('_', ' ', $type))" />@endforeach</div>
<div class="attendance-location-comparison">
<x-attendance-location-map :day="$day" />
<div class="attendance-event-column">@foreach($day['entries'] as $type => $entry)<section class="card min-w-0" data-attendance-event="{{ $type }}"><div class="card-heading"><h2>{{ ucwords(str_replace('_', ' ', $type)) }} details</h2></div><div class="card-body"><x-attendance-event-details :entry="$entry" :hr="$hr" compact /></div></section>@endforeach</div>
</div>
<section class="card min-w-0 attendance-branch-details"><div class="card-heading"><h2>Branch &amp; geofence</h2></div><div class="card-body space-y-4">
<dl class="attendance-location-details event-details">
@foreach(['Recorded branch' => $day['map']['branchName'], 'Branch coordinates' => $day['map']['branch'] ? implode(', ', $day['map']['branch']) : 'Historical branch coordinates are unavailable.', 'Allowed radius (recorded)' => $day['map']['radius'] !== null ? $day['map']['radius'].' metres' : 'Historical geofence radius unavailable', 'Branch geometry shown' => $day['map']['branchSource'] ? $day['map']['branchSource'].' snapshot' : 'Not available', 'Attendance date' => $record->work_date->format('M j, Y')] as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach
</dl><div class="flex flex-wrap gap-2"><x-state :value="$record->session_status" /><x-state :value="$record->verification_status" /><x-state :value="$record->review_status" /></div>
@if($day['differentSnapshots'])<p class="small text-amber-800">Time In and Time Out have different recorded geofence settings. The circle uses the {{ $day['map']['branchSource'] }} snapshot. Each event's original radius, coordinates and result remain in its details.</p>@endif
</div></section>
<details class="card attendance-technical" data-attendance-technical><summary class="p-5">Technical Attendance Location Details</summary><div class="card-body attendance-photo-comparison">
@foreach($day['entries'] as $type => $entry)<section class="min-w-0"><h3 class="font-semibold mb-4">{{ ucwords(str_replace('_', ' ', $type)) }}</h3>@if($entry['evidence'])<dl class="attendance-location-details">@foreach($entry['evidence']['technical'] as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl>@else<p class="small muted">Not available</p>@endif</section>@endforeach
</div></details>
@if(count($day['additional']))<details class="card"><summary class="p-5 small font-semibold text-purple-900 cursor-pointer">Additional submissions ({{ count($day['additional']) }})</summary><div class="card-body stack"><p class="small muted">The comparison uses the accepted submission for each action, or the latest attempt if none is accepted. Earlier attempts and reviews remain below.</p>
@foreach($day['additional'] as $entry)<section class="stack border-t border-slate-100 pt-5"><x-attendance-evidence-photo :entry="$entry" :title="$entry['evidence']['map']['action'].' attempt #'.$entry['event']->id" /><x-attendance-event-details :entry="$entry" :hr="$hr" /><details class="attendance-technical"><summary>Technical Location Details</summary><dl class="attendance-location-details mt-4">@foreach($entry['evidence']['technical'] as $label => $value)<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach</dl></details></section>@endforeach
</div></details>@endif
</div>
<p class="small muted mt-6 leading-6">Photos are visual evidence, not facial verification. GPS can be inaccurate or manipulated. HR approval does not turn a failed location reading into a validated one.</p>
</x-layouts.app>
