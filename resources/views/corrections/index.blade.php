<x-layouts.app title="Attendance Corrections">
<x-page-header :title="$hr ? 'Attendance Corrections' : 'My Attendance Corrections'" description="Track requests to correct attendance, with the original evidence preserved.">
@if(!$hr)<x-button :href="route('employee.corrections.create')">Request correction</x-button>@endif
<x-button variant="secondary" :href="$hr ? route('hr.attendance.index') : route('employee.attendance.history')">Attendance records</x-button></x-page-header>
<section class="card"><form class="toolbar" method="GET"><x-select name="status" label="Status" :options="['pending'=>'Pending','approved'=>'Approved','rejected'=>'Rejected','cancelled'=>'Cancelled']" :value="request('status')" placeholder="All statuses"/><x-button type="submit" variant="secondary">Apply filters</x-button></form>
<x-table :columns="['Employee / branch','Request','Attendance date','Submitted','Status','Details']" caption="Attendance correction requests">
@forelse($corrections as $correction)<tr><td>{{ $correction->employee->full_name }}<span class="small muted block">{{ $correction->branch?->branch_name ?? 'No assigned branch' }}</span></td><td>{{ ucwords(str_replace('_',' ',$correction->correction_type)) }}</td><td>{{ $correction->requested_date->format('M j, Y') }}</td><td>{{ $correction->submitted_at->timezone(config('attendance.timezone'))->format('M j, Y H:i') }}</td><td><x-state :value="$correction->status"/></td><td><a class="text-blue-700" href="{{ route($hr ? 'hr.corrections.show' : 'employee.corrections.show',$correction) }}">View request</a></td></tr>
@empty<tr><td colspan="6"><x-empty-state title="No correction requests" description="Requests you can access will appear here." icon="clock"/></td></tr>@endforelse
</x-table><div class="card-body">{{ $corrections->links() }}</div></section></x-layouts.app>

