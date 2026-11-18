<?php

namespace App\Services;

use App\Models\AttendanceEvent;
use App\Models\AttendanceRecord;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class AttendanceQueries
{
    /** @return list<array{date: string, label: string, time_in: int, time_out: int}> */
    public function activity(): array
    {
        $firstDay = now(config('attendance.timezone'))->startOfDay()->subDays(6);
        $query = AttendanceEvent::where('occurred_at', '>=', $firstDay->copy()->utc())
            ->where('occurred_at', '<', $firstDay->copy()->addDays(7)->utc())
            ->whereIn('verification_status', ['location_validated', 'exception_approved'])->toBase();
        $days = [];
        for ($index = 0; $index < 7; $index++) {
            $day = $firstDay->copy()->addDays($index);
            $days[] = ['date' => $day->toDateString(), 'label' => $day->format('D'), 'time_in' => 0, 'time_out' => 0];
            foreach (['time_in', 'time_out'] as $type) {
                $query->selectRaw("COUNT(CASE WHEN occurred_at >= ? AND occurred_at < ? AND event_type = ? THEN 1 END) AS {$type}_{$index}", [$day->copy()->utc(), $day->copy()->addDay()->utc(), $type]);
            }
        }
        $counts = $query->first();
        foreach ($days as $index => &$day) {
            $day['time_in'] = (int) $counts->{'time_in_'.$index};
            $day['time_out'] = (int) $counts->{'time_out_'.$index};
        }

        return $days;
    }

    public function records(Request $request, ?int $employeeId = null): Builder
    {
        $data = $request->validate(['from' => ['nullable', 'date_format:Y-m-d'], 'to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('from') ? ['after_or_equal:from'] : [])], 'q' => $employeeId === null ? ['nullable', 'string', 'max:100'] : ['prohibited'], 'branch_id' => $employeeId === null ? ['nullable', 'integer', 'exists:branches,id'] : ['prohibited'], 'employee_id' => $employeeId === null ? ['nullable', 'integer', 'exists:employees,id'] : ['prohibited'], 'session_status' => ['nullable', Rule::in(['open', 'pending', 'completed', 'cancelled'])], 'verification_status' => ['nullable', Rule::in(['location_validated', 'pending_review', 'exception_approved', 'rejected'])], 'sort' => ['nullable', Rule::in(['work_date', 'created_at', 'time_in_at'])], 'direction' => ['nullable', Rule::in(['asc', 'desc'])]]);

        return AttendanceRecord::with(['employee.user.employee', 'branch'])
            ->when($employeeId !== null, fn ($query) => $query->where('employee_id', $employeeId))
            ->when($employeeId === null && ! empty($data['employee_id']), fn ($query) => $query->where('employee_id', $data['employee_id']))
            ->when($employeeId === null && ! empty($data['branch_id']), fn ($query) => $query->where('branch_id', $data['branch_id']))
            ->when($employeeId === null && ! empty($data['q']), fn ($query) => $query->whereHas('employee', fn ($employee) => $employee->where(fn ($search) => $search->where('employee_number', 'like', '%'.$data['q'].'%')->orWhere('first_name', 'like', '%'.$data['q'].'%')->orWhere('last_name', 'like', '%'.$data['q'].'%'))))
            ->when($data['from'] ?? null, fn ($query, $date) => $query->where('work_date', '>=', $date))
            ->when($data['to'] ?? null, fn ($query, $date) => $query->where('work_date', '<', Carbon::parse($date)->addDay()->toDateString()))
            ->when($data['session_status'] ?? null, fn ($query, $status) => $query->where('session_status', $status))
            ->when($data['verification_status'] ?? null, fn ($query, $status) => $query->where('verification_status', $status))
            ->orderBy($data['sort'] ?? 'created_at', $data['direction'] ?? 'desc')->orderByDesc('id');
    }

    public function statistics(): array
    {
        $start = now(config('attendance.timezone'))->startOfDay()->utc();
        $end = $start->copy()->addDay();
        $accepted = ['location_validated', 'exception_approved'];
        $events = AttendanceEvent::where('occurred_at', '>=', $start)->where('occurred_at', '<', $end)
            ->toBase()
            ->selectRaw('COUNT(CASE WHEN event_type = ? AND verification_status IN (?, ?) THEN 1 END) AS time_ins', ['time_in', ...$accepted])
            ->selectRaw('COUNT(CASE WHEN event_type = ? AND verification_status IN (?, ?) THEN 1 END) AS time_outs', ['time_out', ...$accepted])
            ->selectRaw('COUNT(CASE WHEN geofence_status != ? THEN 1 END) AS exceptions', ['within_geofence'])
            ->first();
        $reviewCounts = AttendanceEvent::whereIn('review_status', ['pending', 'rejected'])
            ->toBase()->selectRaw('review_status, COUNT(*) AS total')->groupBy('review_status')->pluck('total', 'review_status');
        $warningBefore = now()->subHours(config('attendance.open_warning_hours'));
        $open = AttendanceRecord::where('session_status', 'open')->whereNull('effective_time_out_at')
            ->toBase()->selectRaw('COUNT(*) AS total, COUNT(CASE WHEN time_in_at < ? THEN 1 END) AS overdue', [$warningBefore])->first();
        $completed = AttendanceRecord::where('session_status', 'completed')
            ->where('time_out_at', '>=', $start)->where('time_out_at', '<', $end)->count();

        return [
            'Time Ins today' => (int) $events->time_ins,
            'Time Outs today' => (int) $events->time_outs,
            'Currently clocked in' => (int) $open->total,
            'Completed sessions today' => $completed,
            'Pending HR review' => (int) ($reviewCounts['pending'] ?? 0),
            'Open over 24 hours' => (int) $open->overdue,
            'Rejected submissions' => (int) ($reviewCounts['rejected'] ?? 0),
            'Geofence exceptions today' => (int) $events->exceptions,
        ];
    }
}
