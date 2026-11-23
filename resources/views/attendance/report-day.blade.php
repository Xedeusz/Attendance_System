<x-layouts.app title="Attendance Report Details">
    <x-page-header :title="$row['employee']" :description="'Attendance report for '.$row['date']">
        <x-button variant="secondary" :href="route('hr.attendance-reports.index', $back)">Back to Attendance Reports</x-button>
    </x-page-header>
    <section aria-label="Daily attendance details" class="rounded-xl border border-slate-200 bg-white p-5">
        <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach([
                'Employee ID' => $row['employee_number'], 'Date' => $row['date'], 'Branch' => $row['branch'],
                'Department' => $row['department'], 'Schedule' => $row['schedule'], 'Time zone' => config('attendance.timezone'),
                'Time In' => $row['in'], 'Time Out' => $row['out'],
                'Work Hours' => $row['minutes'] === null ? null : intdiv($row['minutes'], 60).'h '.($row['minutes'] % 60).'m',
                'Late' => $row['late'] === null ? null : $row['late'].'m', 'Undertime' => $row['undertime'] === null ? null : $row['undertime'].'m',
                'Attendance Status' => $row['status'], 'Leave Status' => $row['leave'] ?: null,
            ] as $label => $value)
                <div>
                    <dt class="text-sm font-semibold text-slate-600">{{ $label }}</dt>
                    <dd class="mt-1 text-slate-900">{{ $value ?? '—' }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</x-layouts.app>
