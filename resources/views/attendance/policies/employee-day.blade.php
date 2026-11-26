<div class="space-y-4 text-sm" data-work-date-details>
    <h3 class="text-base font-bold text-(--limcoma-purple)">{{ $selected->format('l, F j, Y') }}</h3>
    <dl class="divide-y divide-slate-100">
        <div class="profile-row"><dt>Attendance requirement</dt><dd>{{ \App\Services\AttendancePolicyResolver::OBLIGATIONS[$policy['obligation']] }}</dd></div>
        @if($schedule = $policy['schedule'])
            @if($policy['obligation'] !== 'work' && !in_array($selected->dayOfWeekIso, $schedule['working_days'], true))
                <div class="profile-row"><dt>Schedule day</dt><dd>Rest day</dd></div>
            @endif
            <div class="profile-row"><dt>Assigned schedule</dt><dd>{{ $schedule['name'] }}</dd></div>
            <div class="profile-row"><dt>Shift hours</dt><dd>{{ substr($schedule['starts_at'], 0, 5) }}–{{ substr($policy['adjusted_end'] ?? $schedule['ends_at'], 0, 5) }}@if(($policy['adjusted_end'] ?? $schedule['ends_at']) < $schedule['starts_at']) (next day)@endif</dd></div>
            @if($schedule['break_starts_at'] && $schedule['break_ends_at'])
                <div class="profile-row"><dt>Scheduled break</dt><dd>{{ intdiv(\App\Services\WorkDuration::offset($schedule['break_ends_at'], $schedule['break_starts_at']), 60) }} min · {{ $schedule['paid_break'] ? 'Paid' : 'Unpaid' }}</dd></div>
            @else
                <div class="profile-row"><dt>Scheduled break</dt><dd>None</dd></div>
            @endif
        @endif
        <div class="profile-row"><dt>Expected net work</dt><dd>{{ $expected === null ? 'Undetermined' : intdiv($expected, 60).'h '.($expected % 60).'m' }}</dd></div>
    </dl>

    @if($policy['holidays'])
        <section class="space-y-2">
            <h4 class="font-semibold">Applicable holidays</h4>
            @foreach($policy['holidays'] as $holiday)
                <div class="rounded-lg border border-amber-100 bg-amber-50 p-3 wrap-anywhere">
                    <p class="font-bold">{{ $holiday['name'] }}</p>
                    <x-attendance-holiday-badge :type="$holiday['type']" />
                    <p class="mt-1">{{ $policy['branch_id'] ? 'Applies to your assigned branch.' : 'Applies company-wide.' }} {{ $holiday['work_expected'] ? 'Follow your assigned schedule.' : 'Non-working unless an approved shift applies.' }}</p>
                </div>
            @endforeach
        </section>
    @endif

    @if($dayLeaves->isNotEmpty())
        <section class="space-y-2">
            <h4 class="font-semibold">Approved leave</h4>
            @foreach($dayLeaves as $leave)
                <div class="rounded-lg bg-violet-50 p-3 wrap-anywhere">
                    <a class="text-link" href="{{ route('employee.leave.show', $leave) }}">{{ $leave->type_snapshot['name'] }}</a>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                        <x-badge tone="teal">Approval status: Approved</x-badge>
                        <x-leave-period :leave="$leave" />
                    </div>
                    <p>{{ $leave->start_date->format('M j, Y') }}–{{ $leave->end_date->format('M j, Y') }} · Number of approved leave days: {{ \App\Support\LeaveDays::format($leave->requested_duration) }}{{ $leave->half_day ? ' ('.strtoupper($leave->half_day).')' : '' }}</p>
                </div>
            @endforeach
        </section>
    @endif

    @if($records->isNotEmpty() || $manualEntries->isNotEmpty())
        <section class="space-y-2">
            <h4 class="font-semibold">Attendance</h4>
            @foreach([...$records, ...$manualEntries] as $entry)
                @php
                    $manual = $entry instanceof \App\Models\AttendanceCorrectionRequest;
                    $in = $manual ? $entry->requested_time_in_at : ($entry->effective_time_in_at ?? $entry->time_in_at);
                    $out = $manual ? $entry->requested_time_out_at : ($entry->effective_time_out_at ?? $entry->time_out_at);
                    $computation = $entry->computation;
                @endphp
                <div class="space-y-1 rounded-lg border border-slate-200 p-3">
                    <a class="text-link" href="{{ route($manual ? 'employee.corrections.show' : 'attendance.show', $entry) }}">{{ $manual ? 'Approved manual entry' : 'Attendance record' }}</a>
                    <p>Time In {{ $in?->timezone($policy['timezone'])->format('g:i A') ?? '—' }} · Time Out {{ $out?->timezone($policy['timezone'])->format('g:i A') ?? '—' }}</p>
                    <x-state :value="$manual || ($entry->correction_version && $out) ? 'approved' : $entry->session_status" />
                    @if($computation)
                        <p>Net work {{ $computation->net_minutes === null ? '—' : $computation->net_minutes.' min' }} · Late {{ $computation->late_minutes === null ? '—' : $computation->late_minutes.' min' }} · Undertime {{ $computation->undertime_minutes === null ? '—' : $computation->undertime_minutes.' min' }}</p>
                    @endif
                </div>
            @endforeach
        </section>
    @endif
</div>
