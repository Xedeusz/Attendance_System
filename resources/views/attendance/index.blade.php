<x-layouts.app :title="$hr ? 'Attendance Monitoring' : 'My Attendance History'">
<x-page-header :title="$hr ? 'Attendance Monitoring' : 'My Attendance History'" :description="$hr ? 'Company-wide records, original evidence, and HR review.' : 'Your own attendance records and review outcomes.'">
@if(!$hr)<x-button variant="secondary" :href="route('employee.attendance')">My attendance</x-button>@endif
</x-page-header>
@if(!$hr && auth()->user()->employee)<x-attendance-identity :employee="auth()->user()->employee" :branch-name="auth()->user()->employee->currentAssignment?->branch?->branch_name" class="mb-6" />@endif
@if($hr)
    @php
        $summary = [
            'Employees present' => 'Employees Present',
            'Employees late' => 'Employees Late',
            'Employees with undertime' => 'Employees with Undertime',
            'Employees on approved leave' => 'Employees on Approved Leave',
            'Incomplete attendance' => 'Incomplete Attendance',
            'Pending HR review' => 'Pending HR Review',
        ];
        $geofenceExceptions = $stats['Geofence exceptions today'];
        $overdueSessions = $stats['Open over 24 hours'];
    @endphp
    <section class="@container/attendance-summary mb-5" aria-label="Attendance overview">
        <dl class="grid auto-rows-fr grid-cols-2 gap-2.5 @min-[34rem]/attendance-summary:grid-cols-3 @min-[57rem]/attendance-summary:grid-cols-6">
            @foreach($summary as $metric => $label)
                <div class="flex min-w-0 flex-col-reverse justify-end gap-1 rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                    <dt class="text-xs font-medium leading-snug text-slate-700">{{ $label }}</dt>
                    <dd class="text-2xl font-bold leading-none text-(--limcoma-purple) tabular-nums">{{ number_format($stats[$metric]) }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-2 text-xs text-slate-600">Daily counts use Asia/Manila. Pending HR review includes all dates.</p>
    </section>
    @if($geofenceExceptions || $overdueSessions)
        <section class="mb-5" aria-label="Attendance alerts">
            <x-alert>
                <ul class="space-y-1 text-sm">
                    @if($geofenceExceptions)
                        <li><strong>{{ number_format($geofenceExceptions) }}</strong> geofence exceptions today. Review the original location evidence in attendance records.</li>
                    @endif
                    @if($overdueSessions)
                        <li><strong>{{ number_format($overdueSessions) }}</strong> sessions open over 24 hours. <a class="text-link" href="{{ route('hr.attendance.index', ['session_status' => 'open']) }}">View open sessions</a></li>
                    @endif
                </ul>
            </x-alert>
        </section>
    @endif
@endif
@unless($hr)
    <div class="action-row mb-6">
        <x-button variant="secondary" :href="route('employee.work-calendar')">My Work Calendar</x-button>
        <x-button variant="secondary" :href="route('employee.corrections.index')">Attendance corrections</x-button>
    </div>
@endunless
<section class="card">
<form method="GET" class="{{ $hr ? '@container/attendance-filters space-y-2 border-b border-slate-200 px-5 py-4' : 'toolbar' }}">
@if($hr)
    <div class="grid grid-cols-1 items-end gap-3 @min-[30rem]/attendance-filters:grid-cols-2 @min-[48rem]/attendance-filters:grid-cols-[repeat(4,minmax(0,1fr))_auto] [&>.form-group]:mb-0! [&_.field]:h-11!">
        <div><label class="field-label" for="q">Employee search</label><input id="q" name="q" class="field" value="{{ request('q') }}" placeholder="Name or employee number"></div><x-select name="branch_id" label="Branch" :options="$branches->pluck('branch_name','id')" :value="request('branch_id')" placeholder="All branches" />@endif
<div><label class="field-label" for="from">Work date from</label><input class="field" type="date" id="from" name="from" value="{{ request('from') }}"></div><div><label class="field-label" for="to">Work date to</label><input class="field" type="date" id="to" name="to" value="{{ request('to') }}"></div>
@if($hr)
        <x-button type="submit" class="h-11 w-full whitespace-nowrap @min-[30rem]/attendance-filters:w-auto @min-[30rem]/attendance-filters:justify-self-start">Apply filters</x-button>
    </div>
@endif
<details class="filter-details" @if(request()->anyFilled(['employee_id', 'session_status', 'verification_status', 'sort', 'direction', 'department_id', 'work_schedule_id', 'holiday_type', 'computed'])) open @endif><summary>More filters</summary><div class="filter-details-fields">@if($hr)<div><label for="employee_id" class="field-label">Employee record ID</label><input type="number" min="1" id="employee_id" name="employee_id" class="field" value="{{ request('employee_id') }}"></div>
    <x-select name="department_id" label="Department" :options="$departments" :value="request('department_id')" placeholder="All departments" />
    <x-select name="work_schedule_id" label="Work schedule version" :options="$schedules" :value="request('work_schedule_id')" placeholder="All schedules" />
    <x-select name="holiday_type" label="Holiday classification" :options="\App\Models\AttendanceHoliday::TYPES" :value="request('holiday_type')" placeholder="All classifications" />
    <x-select name="computed" label="Computed attendance" :options="['late' => 'Late', 'undertime' => 'Undertime', 'incomplete' => 'Incomplete']" :value="request('computed')" placeholder="All outcomes" />
@endif<x-select name="session_status" label="Session status" :options="['open' => 'Open', 'completed' => 'Completed', 'pending' => 'Pending first punch review', 'cancelled' => 'Cancelled / rejected first punch']" :value="request('session_status')" placeholder="All sessions" /><x-select name="verification_status" label="Verification" :options="['location_validated' => 'Location validated', 'pending_review' => 'Pending HR review', 'exception_approved' => 'Exception approved', 'rejected' => 'Rejected']" :value="request('verification_status')" placeholder="All results" /><x-select name="sort" label="Sort" :options="['created_at' => 'Submitted', 'work_date' => 'Work date', 'time_in_at' => 'Time In']" :value="request('sort','created_at')" :placeholder="null" /><x-select name="direction" label="Order" :options="['desc' => 'Newest first', 'asc' => 'Oldest first']" :value="request('direction','desc')" :placeholder="null" /></div></details>
@unless($hr)<x-button type="submit">Apply filters</x-button>@endunless
</form>
<x-table :columns="$hr ? ['Employee', 'Branch', 'Work date', 'Time In', 'Time Out', 'Session', 'Verification', 'Working hours', 'Details'] : ['Work date', 'Branch', 'Time In', 'Time Out', 'Session', 'Verification', 'Working hours', 'Details']" caption="Attendance sessions">
@forelse($records as $record)<tr>@if($hr)<td><div class="flex items-center gap-3"><x-user-avatar :user="$record->employee->user" class="attendance-table-avatar" /><div class="min-w-0"><span class="font-semibold">{{ $record->employee->full_name }}</span><br><span class="small muted">{{ $record->employee->employee_number }}@if($record->employee->department)<br>{{ $record->employee->department->department_name }}@endif</span></div></div></td>@endif<td>{{ $hr ? $record->branch->branch_name : $record->work_date->format('Y-m-d') }}</td><td>{{ $hr ? $record->work_date->format('Y-m-d') : $record->branch->branch_name }}</td><td>{{ ($record->effective_time_in_at ?? $record->time_in_at)?->timezone('Asia/Manila')->format('M j, H:i:s') ?? 'Not accepted' }}</td><td>{{ ($record->effective_time_out_at ?? $record->time_out_at)?->timezone('Asia/Manila')->format('M j, H:i:s') ?? 'Not recorded' }}</td><td>@if($record->correction_version)<x-badge tone="amber">Manual correction</x-badge><span class="small muted block">{{ $record->effective_time_out_at ? 'Effectively closed' : 'Effective times amended' }}</span>@else<x-state :value="$record->session_status"/>@endif</td><td>@if($record->correction_version)<span class="small muted block">Original evidence</span>@endif<x-state :value="$record->verification_status"/></td><td><x-attendance-computation :calculation="$record->computation" compact /></td><td><a class="text-blue-700" href="{{ route('attendance.show',$record) }}">View record</a></td></tr>@empty<tr><td colspan="{{ $hr ? 9 : 8 }}"><x-empty-state title="No attendance records match this view" description="No absence is inferred from an empty list." icon="clock" /></td></tr>@endforelse
</x-table><div class="card-body">{{ $records->links() }}</div></section>
@if($hr)
    <details class="card mt-4">
        <summary class="cursor-pointer px-4 py-3 text-sm font-semibold text-(--limcoma-purple)">Attendance report details</summary>
        <dl class="grid grid-cols-2 gap-3 border-t border-slate-200 px-4 py-3 text-sm sm:grid-cols-3">
            @foreach(array_diff_key($stats, $summary) as $label => $count)
                <div><dt class="text-slate-600">{{ $label }}</dt><dd class="font-semibold tabular-nums">{{ number_format($count) }}</dd></div>
            @endforeach
        </dl>
    </details>
@endif
<section class="card mt-6"><div class="card-heading"><h2>{{ $hr ? 'Pending assistance requests' : 'My recent assistance requests' }}</h2></div><div class="card-body space-y-6">@forelse($assistance as $item)<article class="border border-slate-200 rounded-lg p-4"><div class="flex gap-3 justify-between flex-wrap"><h3>{{ $hr ? $item->employee->full_name : 'HR assistance' }} · {{ str_replace('_',' ',$item->action) }}</h3><x-state :value="$item->status" /></div><p class="small muted mt-3 leading-6">{{ $item->reason }}</p>@if($item->remarks)<p class="small mt-3">HR: {{ $item->remarks }}</p>@endif
@if($hr)<form class="mt-5 space-y-4" method="POST" action="{{ route('hr.attendance.assistance.review',$item) }}">@csrf<x-select name="status" :id="'assist-status-'.$item->id" label="Disposition" :options="['resolved' => 'Addressed / follow-up arranged (no punch created)', 'rejected' => 'Rejected']" :placeholder="null" /><div class="field-label-row"><label class="field-label" for="remarks-assistance-{{ $item->id }}">HR remarks</label><span class="field-required" aria-hidden="true">Required</span></div><textarea class="field attendance-textarea" rows="3" id="remarks-assistance-{{ $item->id }}" name="remarks" required minlength="10" maxlength="1000"></textarea><x-confirm-check label="I confirm this support disposition; it will not create attendance timestamps." /><x-button type="submit" variant="secondary">Save disposition</x-button></form>@endif</article>@empty<p class="small muted">No assistance requests in this view.</p>@endforelse @if($hr){{ $assistance->links() }}@endif</div></section>
@if(isset($manualEntries) && $manualEntries->isNotEmpty())<section class="card mt-6"><div class="card-heading"><h2>Approved manual entries</h2></div><div class="card-body"><p class="small muted mb-4">HR-approved missing-record corrections. Camera and GPS were not verified for these entries.</p>@foreach($manualEntries as $entry)<div class="profile-row"><span>{{ $entry->requested_date->format('M j, Y') }} · {{ $entry->requested_time_in_at?->timezone(config('attendance.timezone'))->format('H:i') }} – {{ $entry->requested_time_out_at?->timezone(config('attendance.timezone'))->format('H:i') ?? 'No Time Out' }}</span><a class="text-link" href="{{ route('employee.corrections.show',$entry) }}">View manual entry #{{ $entry->id }}</a><x-attendance-computation :calculation="$entry->computation" compact /></div>@endforeach</div></section>@endif
</x-layouts.app>
