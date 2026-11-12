<x-layouts.app title="My Workday">
<x-page-header :title="'Welcome, '.$user->name" description="Your employment details, attendance, and review updates." />
<div class="content-grid"><div class="stack">
<section class="card"><div class="card-heading"><h2>My employment details</h2><x-state :value="$user->account_status" /></div><div class="card-body">
@if($user->employee)
@foreach(['Employee number' => $user->employee->employee_number, 'Department' => $user->employee->department->department_name, 'Position' => $user->employee->position_title, 'Primary branch' => $user->employee->currentAssignment?->branch->branch_name ?? 'Unassigned', 'Employment status' => ucfirst($user->employee->employment_status)] as $label => $value)<div class="profile-row"><span class="muted">{{ $label }}</span><span>{{ $value }}</span></div>@endforeach
@else<p class="small muted leading-6">No employee profile is linked to this login. Contact HR. HR personnel can use this same login for self-service once linked to an employee profile.</p>@endif
</div></section>
<section class="card"><div class="card-heading"><h2>Recent attendance</h2><a class="text-blue-700 small" href="{{ route('employee.attendance.history') }}">View history</a></div>
<x-table :columns="['Work date', 'Branch', 'Session', 'Verification']" caption="Your recent attendance">@forelse($records as $record)<tr><td><a class="text-blue-700" href="{{ route('attendance.show', $record) }}">{{ $record->work_date->format('M d, Y') }}</a></td><td>{{ $record->branch->branch_name }}</td><td><x-state :value="$record->session_status" /></td><td><x-state :value="$record->verification_status" /></td></tr>@empty<tr><td colspan="4"><x-empty-state title="No attendance recorded yet" description="Accepted submissions and requests awaiting HR review will appear here." icon="clock" /></td></tr>@endforelse</x-table></section>
</div><div class="stack"><section class="card"><div class="card-heading"><h2>Record attendance</h2></div><div class="card-body"><p class="small muted mb-5 leading-6">Open the attendance page to capture a fresh camera photo and check your reported location. Your official time is recorded by the server.</p>
@if($user->employee)<x-button :href="route('employee.attendance')">Open attendance</x-button>@else<div class="flex gap-3"><x-button disabled>Time In</x-button><x-button disabled>Time Out</x-button></div>@endif
</div></section><section class="card"><div class="card-heading"><h2>Leave applications</h2><x-badge>Coming later</x-badge></div><x-empty-state title="Leave self-service is not enabled" description="No applications or approvals are simulated." icon="calendar" /></section></div></div>
</x-layouts.app>
