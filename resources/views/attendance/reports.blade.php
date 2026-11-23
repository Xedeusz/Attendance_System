<x-layouts.app title="Attendance Reports">
    <x-page-header title="Attendance Reports" description="Generate and export employee attendance reports using selected dates, branches, departments, and employees.">
        <x-button :href="route('hr.attendance-reports.export', $filters)">
            <x-icon name="download" /> Export CSV
        </x-button>
    </x-page-header>

    <form method="GET" action="{{ route('hr.attendance-reports.index') }}" class="rounded-xl border border-slate-200 bg-white p-4 mb-5">
        @if($errors->any())
            <div role="alert" class="mb-4 text-sm text-red-800">
                <ul class="list-disc ps-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            @foreach(['from' => 'Date From', 'to' => 'Date To'] as $field => $label)
                <div class="grid gap-1 text-sm font-semibold">
                    <label for="report-{{ $field }}">{{ $label }}</label>
                    <input class="field" id="report-{{ $field }}" name="{{ $field }}" type="date" value="{{ old($field, $filters[$field]) }}" min="1900-01-01" max="2099-12-31" required @if($errors->has($field)) aria-invalid="true" @endif>
                </div>
            @endforeach
            @foreach(['branch_id' => ['Branch', 'All Branches', $branches], 'department_id' => ['Department', 'All Departments', $departments], 'employee_id' => ['Employee', 'All Employees', $employees->mapWithKeys(fn ($employee) => [$employee->id => $employee->full_name.' · '.$employee->employee_number])]] as $field => [$label, $all, $options])
                <div class="grid min-w-0 gap-1 text-sm font-semibold">
                    <label for="report-{{ $field }}">{{ $label }}</label>
                    <select class="field" id="report-{{ $field }}" name="{{ $field }}" @if($errors->has($field)) aria-invalid="true" @endif>
                        <option value="">{{ $all }}</option>
                        @if($field === 'employee_id' && isset($filters[$field]) && !$options->has((int) $filters[$field]))
                            <option value="{{ $filters[$field] }}" selected>Selected employee outside these filters</option>
                        @endif
                        @foreach($options as $id => $name)
                            <option value="{{ $id }}" @selected((string) old($field, $filters[$field] ?? '') === (string) $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
        </div>
        @if(isset($filters['direction']))
            <input type="hidden" name="direction" value="{{ $filters['direction'] }}">
        @endif
        <div class="mt-4 flex flex-wrap gap-2">
            <x-button type="submit">Apply Filters</x-button>
            <x-button variant="secondary" :href="route('hr.attendance-reports.index')">Reset Filters</x-button>
        </div>
    </form>

    <section aria-label="Attendance summary" class="@container mb-5">
        <dl class="grid grid-cols-2 gap-2.5 @min-[32rem]:grid-cols-3 @min-[58rem]:grid-cols-6">
            @foreach($summary as $metric => $value)
                <div class="attendance-overview-metric">
                    <dt>{{ $metric }}</dt>
                    <dd>{{ $metric === 'Total Work Hours' ? intdiv($value, 60).'h '.($value % 60).'m' : number_format($value, fmod($value, 1) ? 1 : 0) }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-2 text-xs text-slate-600">Workdays count each employee’s scheduled days before approved leave. Present days count scheduled days with a qualifying Time In. Leave is shown in day-equivalents. Work hours sum net minutes from complete attendance.</p>
    </section>

    <section aria-labelledby="report-results" class="rounded-xl border border-slate-200 bg-white">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 px-4 py-3">
            <h2 id="report-results" class="font-semibold text-slate-900">Daily attendance</h2>
            @if($rows->total())
                <p class="text-sm text-slate-600">
                    @if($rows->count()) {{ $rows->firstItem() }}–{{ $rows->lastItem() }} of @endif
                    {{ number_format($rows->total()) }} employee-days
                </p>
            @endif
        </div>
        @if($rows->count())
            <div class="max-h-[65vh] overflow-auto focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-teal-700" tabindex="0" role="region" aria-labelledby="report-results">
                <table class="w-full text-left text-xs">
                    <caption class="sr-only">Daily attendance by employee. Dates and times use {{ config('attendance.timezone') }}.</caption>
                    <thead class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 text-slate-700">
                        <tr>
                            <th scope="col" class="p-2!" aria-sort="{{ ($filters['direction'] ?? 'asc') === 'asc' ? 'ascending' : 'descending' }}">
                                <a class="underline underline-offset-2" href="{{ route('hr.attendance-reports.index', [...$filters, 'direction' => ($filters['direction'] ?? 'asc') === 'asc' ? 'desc' : 'asc']) }}">Date <span aria-hidden="true">{{ ($filters['direction'] ?? 'asc') === 'asc' ? '↑' : '↓' }}</span></a>
                            </th>
                            @foreach(['Employee', 'Employee ID', 'Branch', 'Department', 'Schedule', 'Time In', 'Time Out', 'Work Hours', 'Late', 'Undertime', 'Status', 'Action'] as $label)
                                <th scope="col" class="p-2!">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach($rows as $row)
                            <tr>
                                <td class="p-2! whitespace-nowrap!">{{ $row['date'] }}</td>
                                <th scope="row" class="sticky start-0 max-w-36 bg-white! p-2! font-semibold whitespace-normal!">{{ $row['employee'] }}</th>
                                @foreach(['employee_number', 'branch', 'department', 'schedule'] as $field)
                                    <td class="p-2!">{{ $row[$field] ?? '—' }}</td>
                                @endforeach
                                @foreach(['in', 'out'] as $field)
                                    <td class="p-2! whitespace-nowrap!">
                                        @if($row[$field])
                                            <time datetime="{{ str_replace(' ', 'T', $row[$field]) }}">{{ substr($row[$field], 11, 5) }}</time>
                                            @if(substr($row[$field], 0, 10) !== $row['date'])
                                                <span class="block text-xs">{{ substr($row[$field], 0, 10) }}</span>
                                            @endif
                                        @else
                                            —
                                        @endif
                                    </td>
                                @endforeach
                                <td class="p-2! whitespace-nowrap!">{{ $row['minutes'] === null ? '—' : intdiv($row['minutes'], 60).'h '.($row['minutes'] % 60).'m' }}</td>
                                @foreach(['late', 'undertime'] as $field)
                                    <td class="p-2! whitespace-nowrap!">{{ $row[$field] === null ? '—' : $row[$field].'m' }}</td>
                                @endforeach
                                <td class="min-w-24 p-2!">
                                    {{ $row['status'] }}
                                    @if($row['leave'] && $row['leave'] !== $row['status']) <span class="block">{{ $row['leave'] }}</span> @endif
                                </td>
                                <td class="p-2!">
                                    <a class="whitespace-nowrap font-semibold text-teal-800 underline underline-offset-2" href="{{ route('hr.attendance-reports.show', ['employee' => $row['employee_id'], 'date' => $row['date'], ...$filters, 'page' => $rows->currentPage()]) }}" aria-label="View details for {{ $row['employee'] }} on {{ $row['date'] }}">View Details</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @elseif($rows->total())
            <p class="p-4 text-sm">This page has no rows. <a class="underline" href="{{ $rows->url(1) }}">Return to page 1</a>.</p>
        @else
            <div class="p-6">
                <h3 class="font-semibold">No attendance records found</h3>
                <p class="mt-1 text-sm text-slate-600">Try adjusting the date range, branch, department, or employee.</p>
            </div>
        @endif
        @if($rows->hasPages() && $rows->count())
            <div class="px-4 py-3">{{ $rows->links() }}</div>
        @endif
    </section>
</x-layouts.app>
