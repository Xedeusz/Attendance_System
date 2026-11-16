<x-layouts.app title="HR Dashboard">
<div class="workforce-dashboard">
    <header class="workforce-header">
        <div><p class="workforce-eyebrow">People &amp; operations</p><h1>Workforce Overview</h1><p>Monitor employees, branches, and attendance across LIMCOMA.</p></div>
        <div class="workforce-header-tools"><p class="workforce-date"><x-icon name="calendar" /><time datetime="{{ $businessDate->toIso8601String() }}">{{ $businessDate->format('D, M j, Y') }}</time><span>{{ config('attendance.timezone') }}</span></p><div class="workforce-actions"><x-button :href="route('hr.attendance.index')" variant="secondary">Monitor attendance</x-button><x-button :href="route('hr.employees.create')"><x-icon name="plus" /> Register employee</x-button></div></div>
    </header>
    <section class="card"><div class="card-heading"><h2>Leave &amp; correction queue</h2><a class="text-link" href="{{ route('hr.leave.index') }}">Monitor leave</a></div><dl class="operations-summary card-body">@foreach($leaveStats as $label=>$count)<div><dt>{{ $label }}</dt><dd>{{ $count }}</dd></div>@endforeach</dl><div class="card-body"><a class="text-link" href="{{ route('hr.corrections.index') }}">Review attendance corrections</a></div></section>
    <section class="workforce-kpis" aria-label="Company overview">
        @foreach([
            ['Total employees', 'users', 'hr.employees.index', 'Active employee accounts: '.$stats['Active employee accounts']],
            ['Active branches', 'building', 'hr.branches.index', 'Active office locations'],
            ['Departments', 'grid', 'hr.departments.index', 'Company departments'],
            ['Pending account activations', 'lock', 'hr.employees.index', 'Accounts awaiting activation'],
        ] as [$label, $icon, $route, $context])
            <article class="workforce-kpi" data-metric="{{ $label }}"><div class="workforce-kpi-top"><h2>{{ $label }}</h2><span class="workforce-icon"><x-icon :name="$icon" /></span></div><strong>{{ number_format($stats[$label]) }}</strong><p>{{ $context }}</p><a href="{{ route($route, $label === 'Pending account activations' ? ['account_status' => 'pending_activation'] : []) }}" aria-label="View {{ $label }}"><x-icon name="arrow" /></a></article>
        @endforeach
    </section>
    <section class="workforce-panel workforce-attendance" aria-labelledby="today-heading">
        <div class="workforce-panel-heading"><div><p class="workforce-eyebrow">Daily operations</p><h2 id="today-heading">Today's Attendance</h2></div><span class="workforce-period">{{ $businessDate->format('M j') }} · {{ config('attendance.timezone') }}</span></div>
        <div class="workforce-attendance-primary">
            @foreach([
                ['Currently clocked in', 'clock', 'teal', 'Open attendance sessions'],
                ['Completed sessions today', 'check', 'purple', 'Sessions with a Time Out today'],
                ['Pending HR review', 'file', 'amber', 'Submissions awaiting a decision · all dates'],
            ] as [$label, $icon, $tone, $context])
                <div class="workforce-attendance-metric {{ $attendanceStats[$label] > 0 ? 'workforce-tone-'.$tone : '' }}" data-metric="{{ $label }}"><span class="workforce-icon"><x-icon :name="$icon" /></span><div><h3>{{ $label }}</h3><strong>{{ number_format($attendanceStats[$label]) }}</strong><p>{{ $context }}</p></div></div>
            @endforeach
        </div>
        <dl class="workforce-attendance-secondary">@foreach(['Time Ins today', 'Time Outs today', 'Open over 24 hours', 'Rejected submissions', 'Geofence exceptions today'] as $label)<div data-metric="{{ $label }}"><dt>{{ $label }}</dt><dd>{{ number_format($attendanceStats[$label]) }}</dd></div>@endforeach</dl>
        <p class="workforce-note">Punch totals include location-validated and approved exceptions. Open and review counts cover all dates; rejected submissions are an all-time total.</p>
    </section>
    <div class="workforce-insights">
        <section class="workforce-panel" aria-labelledby="activity-heading">
            @php
                $activityTotal = array_sum(array_column($activity, 'time_in')) + array_sum(array_column($activity, 'time_out'));
                $activityMax = max(1, ...array_column($activity, 'time_in'), ...array_column($activity, 'time_out'));
            @endphp
            <div class="workforce-panel-heading"><div><h2 id="activity-heading">Attendance Activity</h2><p>Review recorded Time In and Time Out activity.</p></div><span class="workforce-period">Last 7 days</span></div>
            @if($activityTotal > 0)
                <div class="workforce-chart-legend"><span><i class="workforce-key-in"></i>Time Ins</span><span><i class="workforce-key-out"></i>Time Outs</span></div>
                <div class="workforce-chart" role="group" aria-label="Accepted attendance events over the last seven days in {{ config('attendance.timezone') }}. Exact counts are available in the data table below.">
                    <div class="workforce-chart-axis" aria-hidden="true"><span>{{ $activityMax }}</span><span>0</span></div>
                    @foreach($activity as $day)
                        <div class="workforce-chart-day"><div class="workforce-chart-bars">@foreach(['time_in' => 'Time Ins', 'time_out' => 'Time Outs'] as $type => $label)<button type="button" class="workforce-chart-bar {{ $type === 'time_in' ? 'workforce-key-in' : 'workforce-key-out' }}" style="--bar-height: {{ $day[$type] / $activityMax * 100 }}%" aria-label="{{ $day['date'] }}: {{ $day[$type] }} {{ $label }}"><span class="workforce-chart-tooltip">{{ $day['date'] }}<br>{{ $label }}: {{ $day[$type] }}</span></button>@endforeach</div><span>{{ $day['label'] }}</span><small>{{ \Illuminate\Support\Carbon::parse($day['date'])->format('M j') }}</small></div>
                    @endforeach
                </div>
                <details class="workforce-chart-data"><summary>View activity data</summary><x-table :columns="['Date', 'Time Ins', 'Time Outs']" caption="Seven-day attendance activity">@foreach($activity as $day)<tr><th scope="row">{{ $day['date'] }}</th><td>{{ $day['time_in'] }}</td><td>{{ $day['time_out'] }}</td></tr>@endforeach</x-table></details>
            @else
                <div class="workforce-empty"><span class="workforce-icon"><x-icon name="chart" /></span><h3>No attendance activity recorded yet</h3><p>Attendance trends will appear here as employees record Time In and Time Out.</p></div>
            @endif
        </section>
        <section class="workforce-panel" aria-labelledby="attention-heading">
            <div class="workforce-panel-heading"><div><p class="workforce-eyebrow">Your next steps</p><h2 id="attention-heading">Requires Attention</h2></div><span class="workforce-icon"><x-icon name="bell" /></span></div>
            @php
                $attention = array_filter([
                    ['count' => $attendanceStats['Pending HR review'], 'title' => 'Attendance pending HR review', 'detail' => 'Review submitted evidence', 'icon' => 'file', 'tone' => 'amber', 'url' => route('hr.attendance.index', ['verification_status' => 'pending_review'])],
                    ['count' => $attendanceStats['Open over 24 hours'], 'title' => 'Open sessions over 24 hours', 'detail' => 'Inspect open attendance sessions', 'icon' => 'clock', 'tone' => 'red', 'url' => route('hr.attendance.index', ['session_status' => 'open'])],
                    ['count' => $stats['Pending account activations'], 'title' => 'Pending account activations', 'detail' => 'View employee account details', 'icon' => 'lock', 'tone' => 'purple', 'url' => route('hr.employees.index', ['account_status' => 'pending_activation'])],
                ], fn ($item) => $item['count'] > 0);
            @endphp
            @forelse($attention as $item)
                <a class="workforce-attention-item workforce-tone-{{ $item['tone'] }}" href="{{ $item['url'] }}"><span class="workforce-icon"><x-icon :name="$item['icon']" /></span><span><strong>{{ $item['title'] }}</strong><small>{{ $item['detail'] }}</small></span><b>{{ number_format($item['count']) }}</b><x-icon name="chevron" /></a>
            @empty
                <div class="workforce-empty"><span class="workforce-icon"><x-icon name="check" /></span><h3>All caught up</h3><p>No pending items require your attention.</p></div>
            @endforelse
            <p class="workforce-note">Counts may overlap. Open-session links show all open sessions for inspection.</p>
        </section>
    </div>
    <section class="workforce-panel" aria-labelledby="recent-heading">
        <div class="workforce-panel-heading"><div><h2 id="recent-heading">Recently Added Employees</h2><p>The five newest records in your employee directory.</p></div><a class="workforce-text-link" href="{{ route('hr.employees.index') }}">View all employees <x-icon name="arrow" /></a></div>
        <x-table :columns="['Employee', 'Department', 'Assigned branch', 'Account status', 'Date added']" caption="Recent employee records">
            @forelse($recent as $employee)
                <tr><td><a class="workforce-person" href="{{ route('hr.employees.show', $employee) }}"><span class="avatar">{{ mb_strtoupper(mb_substr($employee->first_name, 0, 1).mb_substr($employee->last_name, 0, 1)) }}</span><span><strong>{{ $employee->full_name }}</strong><small>{{ $employee->employee_number }}</small></span></a></td><td>{{ $employee->department->department_name }}</td><td>{{ $employee->currentAssignment?->branch->branch_name ?? 'Unassigned' }}</td><td><x-state :value="$employee->user->account_status" /></td><td>{{ $employee->created_at->timezone(config('attendance.timezone'))->format('M j, Y') }}</td></tr>
            @empty
                <tr><td colspan="5"><div class="workforce-empty"><span class="workforce-icon"><x-icon name="users" /></span><h3>No recent employee records.</h3><p>Register employees after configuring branches and departments.</p><a class="workforce-text-link" href="{{ route('hr.employees.create') }}">Register employee <x-icon name="arrow" /></a></div></td></tr>
            @endforelse
        </x-table>
    </section>
    <div class="workforce-insights workforce-details">
        <section class="workforce-panel" aria-labelledby="branches-heading"><div class="workforce-panel-heading"><div><h2 id="branches-heading">Branch Overview</h2><p>Current primary assignments across up to ten offices.</p></div><a class="workforce-text-link" href="{{ route('hr.branches.index') }}">Manage branches <x-icon name="arrow" /></a></div><x-table :columns="['Branch', 'Assigned employees', 'Status']" caption="Company branch overview">@forelse($branches as $branch)<tr><td><a href="{{ route('hr.branches.show', $branch) }}">{{ $branch->branch_name }}</a></td><td>{{ $branch->current_assignments_count }}</td><td><x-state :value="$branch->status" /></td></tr>@empty<tr><td colspan="3"><div class="workforce-empty"><h3>No branches configured</h3><p>Add an official office to begin assigning employees.</p><a class="workforce-text-link" href="{{ route('hr.branches.create') }}">Add branch <x-icon name="arrow" /></a></div></td></tr>@endforelse</x-table></section>
        <section class="workforce-panel" aria-labelledby="activation-heading"><div class="workforce-panel-heading"><div><h2 id="activation-heading">Pending Activations</h2><p>Newest accounts awaiting employee activation.</p></div></div><div class="workforce-activation-list">@forelse($pending as $employee)<a href="{{ route('hr.employees.show', $employee) }}" class="workforce-activation"><span><strong>{{ $employee->full_name }}</strong><small>Added {{ $employee->created_at->timezone(config('attendance.timezone'))->format('M j, Y') }}</small></span><x-state value="pending_activation" /></a>@empty<div class="workforce-empty"><span class="workforce-icon"><x-icon name="lock" /></span><h3>No pending account activations.</h3><p>Accounts awaiting activation will appear here.</p></div>@endforelse</div></section>
    </div>
    <nav class="workforce-shortcuts" aria-label="Workforce shortcuts"><span>Workspace tools</span><a href="{{ route('hr.departments.index') }}">Departments <x-icon name="arrow" /></a><a href="{{ route('hr.accounts.index') }}">HR accounts <x-icon name="arrow" /></a><a href="{{ route('hr.audit') }}">Audit logs <x-icon name="arrow" /></a><a href="{{ route('employee.dashboard') }}">My Workday <x-icon name="arrow" /></a></nav>
</div>
</x-layouts.app>
