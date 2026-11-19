<x-layouts.app title="My Workday">
    <x-page-header title="My Workday" description="Your attendance, assigned branch, and recent records.">
        <x-button variant="secondary" :href="route('employee.work-calendar')">My Work Calendar</x-button>
    </x-page-header>

    @php
        $calculation = $attendanceRecord?->computation;
        $earlierSession = $attendanceRecord && $attendanceRecord->work_date->toDateString() !== $workday->toDateString();
        $todayCalculation = $dayComputation ?? ($earlierSession ? null : $calculation);
        $displayPolicy = $todayCalculation?->policy ?? $workPolicy;
        [$statusLabel, $statusTone, $statusIcon, $attendanceMessage] = match (true) {
            $employee === null => ['Profile required', 'neutral', 'info', 'Contact HR to link your employee profile before recording attendance.'],
            $attendanceRecord?->review_status === 'pending' => ['Awaiting HR review', 'warning', 'info', 'Your submission is saved. HR must complete the review before you can record another action.'],
            $open !== null => ['Clocked In', 'teal', 'clock', 'Your attendance session is open. Open attendance to record Time Out.'],
            $attendanceRecord?->session_status === 'completed' || $attendanceRecord?->effective_time_out_at !== null => ['Clocked Out', 'neutral', 'check', 'Your Time Out is recorded. Review your saved workday in Attendance history.'],
            $attendanceRecord?->session_status === 'cancelled' => ['Cancelled', 'danger', 'info', 'Your latest attendance session was cancelled. Open attendance to check your available actions.'],
            $manualAttendance => ['Approved Manual Attendance', 'teal', 'check', 'An HR-approved manual attendance entry is available in your history.'],
            ($displayPolicy['obligation'] ?? null) === 'not_scheduled' => [$displayPolicy['holidays'] ? 'Holiday · No Work Scheduled' : 'No Work Scheduled', 'neutral', 'calendar', 'There is no scheduled attendance obligation for this date.'],
            ($displayPolicy['obligation'] ?? null) === 'approved_leave' => ['Approved Leave', 'teal', 'calendar', 'Your approved leave covers this workday.'],
            ($displayPolicy['obligation'] ?? null) === 'excused' => ['Authorized Absence', 'teal', 'calendar', 'HR approved an attendance exception for this date.'],
            default => ['Not Clocked In', 'neutral', 'clock', 'No attendance recorded for today. Open attendance to start your workday.'],
        };

        $timezone = $workday->getTimezone();
        $timeIn = ($attendanceRecord?->effective_time_in_at ?? $attendanceRecord?->time_in_at)?->copy()->timezone($timezone);
        $timeOut = ($attendanceRecord?->effective_time_out_at ?? $attendanceRecord?->time_out_at)?->copy()->timezone($timezone);
    @endphp

    <div class="@container/workday">
        <div class="mb-6 grid gap-4 @min-[43rem]/workday:grid-cols-[minmax(0,1fr)_minmax(0,1.12fr)] @min-[43rem]/workday:gap-5">
            <x-employee-identity-card :employee="$employee" :user="$user" />

            <section class="card workday-panel flex min-w-0 flex-col" aria-labelledby="workday-attendance-heading">
                <header class="flex flex-wrap items-start justify-between gap-3 border-b border-slate-200 px-4 py-4 sm:px-5">
                    <div class="min-w-0">
                        <h2 id="workday-attendance-heading" class="text-base font-bold text-(--limcoma-purple)">My Attendance</h2>
                        <p class="mt-1 text-xs text-(--text-secondary)">Manage your workday attendance.</p>
                    </div>
                    <x-badge :tone="$statusTone" class="workday-status gap-1.5">
                        <x-icon :name="$statusIcon" />
                        {{ $statusLabel }}
                    </x-badge>
                </header>

                <div class="@container/attendance flex flex-1 flex-col gap-3.5 px-4 py-4 sm:px-5">
                    <div class="flex min-w-0 flex-wrap items-center justify-between gap-x-3 gap-y-1">
                        <p class="flex min-w-0 items-center gap-2 text-sm font-semibold text-(--text-primary)">
                            <x-icon name="calendar" class="size-4 shrink-0 text-(--limcoma-teal-dark)" />
                            <time datetime="{{ $workday->toDateString() }}">{{ $workday->format('D, M j, Y') }}</time>
                        </p>
                        <span class="text-xs text-(--text-secondary)">{{ $timezone->getName() }}</span>
                    </div>

                    @if($employee)
                        @if($open && $open->work_date->toDateString() !== $workday->toDateString())
                            <p class="text-xs text-(--text-secondary)">Open session from <time datetime="{{ $open->work_date->toDateString() }}">{{ $open->work_date->format('M j, Y') }}</time></p>
                        @endif

                        <dl class="grid grid-cols-2 gap-2.5">
                            @foreach (['Time In' => $timeIn, 'Time Out' => $timeOut] as $label => $timestamp)
                                <div class="min-w-0 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2.5 sm:px-4">
                                    <dt class="text-xs text-(--text-secondary)">{{ $label }}</dt>
                                    <dd class="mt-1 wrap-anywhere text-base font-semibold tabular-nums text-(--text-primary)">
                                        @if($timestamp)
                                            <time datetime="{{ $timestamp->toIso8601String() }}">{{ $timestamp->format('g:i A') }}</time>
                                        @else
                                            <span class="text-sm font-medium text-(--text-secondary)">Not recorded</span>
                                        @endif
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif

                    @if($displayPolicy)
                        <x-attendance-computation :calculation="$todayCalculation" :policy="$displayPolicy" :expected="$expectedToday" compact />
                    @endif
                    @if($earlierSession && $calculation)
                        <details class="text-sm">
                            <summary>Open session policy for {{ $attendanceRecord->work_date->format('M j, Y') }}</summary>
                            <div class="mt-2"><x-attendance-computation :calculation="$calculation" compact /></div>
                        </details>
                    @endif
                    <p class="text-sm leading-relaxed text-slate-600">{{ $attendanceMessage }}</p>

                    <div class="mt-auto grid grid-cols-1 gap-2.5 @min-[23rem]/attendance:flex @min-[23rem]/attendance:flex-wrap">
                        @if($employee)
                            <x-button :href="route('employee.attendance')" class="justify-center"><x-icon name="clock" />Open attendance</x-button>
                            <x-button :href="route('employee.attendance.history')" variant="secondary" class="justify-center"><x-icon name="calendar" />Attendance history</x-button>
                        @else
                            <x-button disabled>Time In</x-button>
                            <x-button disabled variant="secondary">Time Out</x-button>
                        @endif
                    </div>
                </div>
            </section>
        </div>
    </div>

    <div class="workday-layout">
        <section class="card">
            <div class="card-heading">
                <h2>Recent attendance</h2>
                <a class="text-link" href="{{ route('employee.attendance.history') }}">View history <x-icon name="arrow" /></a>
            </div>
            <x-table :columns="['Work date', 'Branch', 'Session', 'Verification']" caption="Your recent attendance">
                @forelse($records as $record)
                    <tr>
                        <td><a class="text-blue-700" href="{{ route('attendance.show', $record) }}">{{ $record->work_date->format('M d, Y') }}</a></td>
                        <td>{{ $record->branch->branch_name }}</td>
                        <td>
                            @if($record->correction_version)
                                <x-badge tone="warning">Manual correction</x-badge>
                                <span class="small muted block">{{ $record->effective_time_out_at ? 'Effectively closed' : 'Effective times amended' }}</span>
                            @else
                                <x-state :value="$record->session_status" />
                            @endif
                        </td>
                        <td>
                            @if($record->correction_version)<span class="small muted block">Original evidence</span>@endif
                            <x-state :value="$record->verification_status" />
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4"><x-empty-state title="No attendance recorded yet" description="Your saved attendance and HR review outcomes will appear here." icon="clock" /></td></tr>
                @endforelse
            </x-table>
        </section>

        <section class="card">
            <div class="card-heading">
                <h2>Recent leave status</h2>
                <x-button variant="secondary" :href="route('employee.leave.create')">Apply for Leave</x-button>
            </div>
            <div class="card-body">
                @forelse($recentLeave as $leave)
                    <div class="profile-row">
                        <a class="text-link" href="{{ route('employee.leave.show', $leave) }}">{{ $leave->type_snapshot['name'] }} · {{ $leave->start_date->format('M j, Y') }}</a>
                        <span class="inline-flex flex-wrap items-center gap-1.5"><x-state :value="$leave->status" /><x-leave-period :leave="$leave" /></span>
                    </div>
                @empty
                    <p class="small muted">No leave applications submitted.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-layouts.app>
