<x-layouts.app title="My Workday">
<x-page-header title="My Workday" description="Your attendance, assigned branch, and recent records." />
<div class="workday-layout">
    <div class="stack">
        <section class="card workday-focus" aria-labelledby="workday-attendance-heading">
            <div class="card-heading"><div><p class="section-kicker">Attendance</p><h2 id="workday-attendance-heading">{{ $open?->review_status === 'pending' ? 'Awaiting HR review' : ($open ? 'Your attendance session is open' : 'Ready for your workday') }}</h2></div><x-state :value="$open?->session_status ?? 'not_clocked_in'" /></div>
            <div class="card-body">
                <p class="workday-name">{{ $user->employee?->full_name ?? $user->name }}</p>
                @if($user->employee)
                    <p class="muted small">{{ $user->employee->currentAssignment?->branch->branch_name ?? 'No primary branch assigned' }}</p>
                    @if($open?->time_in_at)<p class="workday-timestamp">Time In: {{ $open->time_in_at->timezone(config('attendance.timezone'))->format('M j, Y · g:i A') }} <span>{{ config('attendance.timezone') }}</span></p>@endif
                    <p class="workday-guidance">{{ $open?->review_status === 'pending' ? 'Your submission is saved. HR must complete the review before you can record another action.' : ($open ? 'Open attendance to record your next action with a fresh camera photo and location.' : 'No open attendance session. Open attendance to check your available actions and record Time In.') }}</p>
                    <div class="workday-actions"><x-button :href="route('employee.attendance')"><x-icon name="clock" />Open attendance</x-button><a class="text-link" href="{{ route('employee.attendance.history') }}">Attendance history <x-icon name="arrow" /></a></div>
                @else
                    <p class="workday-guidance">No employee profile is linked to this login. Contact HR. HR personnel can use this same login for self-service once linked to an employee profile.</p>
                    <div class="workday-actions"><x-button disabled>Time In</x-button><x-button disabled variant="secondary">Time Out</x-button></div>
                @endif
            </div>
        </section>
        <section class="card"><div class="card-heading"><h2>Recent attendance</h2><a class="text-link" href="{{ route('employee.attendance.history') }}">View history <x-icon name="arrow" /></a></div>
            <x-table :columns="['Work date', 'Branch', 'Session', 'Verification']" caption="Your recent attendance">@forelse($records as $record)<tr><td><a class="text-blue-700" href="{{ route('attendance.show', $record) }}">{{ $record->work_date->format('M d, Y') }}</a></td><td>{{ $record->branch->branch_name }}</td><td>@if($record->correction_version)<x-badge tone="warning">Manual correction</x-badge><span class="small muted block">{{ $record->effective_time_out_at ? 'Effectively closed' : 'Effective times amended' }}</span>@else<x-state :value="$record->session_status"/>@endif</td><td>@if($record->correction_version)<span class="small muted block">Original evidence</span>@endif<x-state :value="$record->verification_status"/></td></tr>@empty<tr><td colspan="4"><x-empty-state title="No attendance recorded yet" description="Your saved attendance and HR review outcomes will appear here." icon="clock" /></td></tr>@endforelse</x-table>
        </section>
    </div>
    <div class="stack">
        <section class="card"><div class="card-heading"><h2>My employment details</h2><x-state :value="$user->account_status" /></div><div class="card-body">
            @if($user->employee)
                @foreach(['Employee number' => $user->employee->employee_number, 'Department' => $user->employee->department->department_name, 'Position' => $user->employee->position_title, 'Primary branch' => $user->employee->currentAssignment?->branch->branch_name ?? 'Unassigned', 'Employment status' => ucfirst($user->employee->employment_status)] as $label => $value)<div class="profile-row"><span class="muted">{{ $label }}</span><span>{{ $value }}</span></div>@endforeach
            @else<p class="small muted leading-6">Your employment information will appear when HR links a profile.</p>@endif
            <a class="text-link mt-5" href="{{ route('employee.profile') }}">View my profile <x-icon name="arrow" /></a>
        </div></section>
        <section class="card"><div class="card-heading"><h2>Leave &amp; support</h2></div><div class="card-body"><a class="utility-link" href="{{ route('notifications') }}"><x-icon name="bell" /><span>Notifications &amp; review updates</span><x-icon name="chevron" /></a><a class="utility-link" href="{{ route('settings') }}"><x-icon name="settings" /><span>Account settings</span><x-icon name="chevron" /></a><a class="utility-link" href="{{ route('employee.leave.index') }}"><x-icon name="calendar"/><span>My Leave · {{ $pendingLeave }} pending</span><x-icon name="chevron"/></a><a class="utility-link" href="{{ route('employee.corrections.index') }}"><x-icon name="file"/><span>Attendance corrections</span><x-icon name="chevron"/></a></div></section>
    </div>
</div>
<section class="card mt-6"><div class="card-heading"><h2>Recent leave status</h2><x-button variant="secondary" :href="route('employee.leave.create')">Apply for Leave</x-button></div><div class="card-body">@forelse($recentLeave as $leave)<div class="profile-row"><a class="text-link" href="{{ route('employee.leave.show',$leave) }}">{{ $leave->type_snapshot['name'] }} · {{ $leave->start_date->format('M j, Y') }}</a><x-state :value="$leave->status"/></div>@empty<p class="small muted">No leave applications submitted.</p>@endforelse</div></section>
</x-layouts.app>
