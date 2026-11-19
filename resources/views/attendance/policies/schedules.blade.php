<x-layouts.app title="Schedules & Policies">
    <x-page-header title="Schedules & Policies" description="Manage effective-dated work schedules and approve individual attendance exceptions.">
        <x-button :href="route('hr.schedules.create')">New schedule</x-button>
    </x-page-header>
    <x-form-errors />
    <section class="card mb-6">
        <div class="card-heading"><h2>Working schedules</h2></div>
        <p class="card-body small muted">Assignment priority is employee, then department, then branch, then company default. Only one schedule can cover an assignment on a given date. Full-day approved leave and excused days take precedence over shift overrides and holidays. An approved shift can override a non-working holiday or a rest day. Schedule times use {{ config('attendance.timezone') }}.</p>
        <x-table :columns="['Schedule', 'Assignment', 'Shift', 'Effective dates', 'Status', 'Actions']" caption="Work schedule versions">
            @forelse($schedules as $schedule)
                <tr>
                    <th scope="row">{{ $schedule->name }} <span class="small muted">#{{ $schedule->id }}</span></th>
                    <td>{{ $schedule->employee?->full_name ?? $schedule->department?->department_name ?? $schedule->branch?->branch_name ?? 'Company default' }}</td>
                    <td>{{ substr($schedule->starts_at, 0, 5) }}–{{ substr($schedule->ends_at, 0, 5) }}</td>
                    <td>{{ $schedule->effective_from->toDateString() }} to {{ $schedule->effective_until?->toDateString() ?? 'Open ended' }}</td>
                    <td>{{ !$schedule->active ? 'Inactive' : ($schedule->effective_until?->toDateString() < now(config('attendance.timezone'))->toDateString() && $schedule->effective_until ? 'Ended' : 'Active') }}</td>
                    <td><a class="text-link" href="{{ route('hr.schedules.edit', $schedule) }}">View / revise</a>
                        @if($schedule->active && (!$schedule->effective_until || $schedule->effective_until->toDateString() >= now(config('attendance.timezone'))->toDateString()))
                            <form method="POST" action="{{ route('hr.schedules.deactivate', $schedule) }}" class="mt-2">
                                @csrf @method('PATCH')
                                <x-confirm-check label="Deactivate from today" />
                                <button class="text-link mt-1" type="submit">Deactivate</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6"><x-empty-state title="No schedules configured" description="Create and assign an HR-approved schedule to calculate expected hours." icon="clock" /></td></tr>
            @endforelse
        </x-table>
        <div class="card-body">{{ $schedules->links() }}</div>
    </section>
    <section class="card mb-6">
        <div class="card-heading"><h2>Approve an attendance exception</h2></div>
        <form method="POST" action="{{ route('hr.schedule-exceptions.store') }}" class="card-body space-y-4">
            @csrf
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-select name="employee_id" label="Employee" :options="$employees" required />
                <x-input name="work_date" label="Work date" type="date" required />
                <x-select name="kind" label="Exception" :options="\App\Models\WorkScheduleException::KINDS" required />
                <x-select name="work_schedule_id" label="Schedule version (shift override only)" :options="$shiftOptions" />
                <x-input name="adjusted_end" label="Authorized end time (early departure only)" type="time" />
                <x-input name="reason" label="Approval reason" minlength="10" maxlength="1000" required />
            </div>
            <x-button type="submit">Approve exception</x-button>
        </form>
    </section>
    <section class="card">
        <div class="card-heading"><h2>Attendance exceptions</h2></div>
        <x-table :columns="['Employee', 'Date', 'Exception', 'Reason', 'Status']" caption="Approved attendance exceptions">
            @forelse($exceptions as $exception)
                <tr><th scope="row">{{ $exception->employee->full_name }}</th><td>{{ $exception->work_date->toDateString() }}</td>
                    <td>{{ \App\Models\WorkScheduleException::KINDS[$exception->kind] }}<br>{{ $exception->workSchedule?->name ?? $exception->adjusted_end }}</td>
                    <td>{{ $exception->reason }}</td>
                    <td>@if($exception->active)
                        <form method="POST" action="{{ route('hr.schedule-exceptions.cancel', $exception) }}">
                            @csrf @method('PATCH')
                            <x-confirm-check label="Deactivate exception" />
                            <button type="submit" class="text-link mt-1">Deactivate</button>
                        </form>
                    @else Inactive @endif</td>
                </tr>
            @empty <tr><td colspan="5" class="muted">No attendance exceptions.</td></tr> @endforelse
        </x-table>
        <div class="card-body">{{ $exceptions->links() }}</div>
    </section>
</x-layouts.app>
