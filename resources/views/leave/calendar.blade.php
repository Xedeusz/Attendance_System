@php
    $title = match ($role) {
        'hr' => 'Leave Calendar',
        'supervisor' => 'Team Leave Calendar',
        default => 'My Leave Calendar',
    };
    $description = match ($role) {
        'hr' => 'Monitor leave schedules across branches and departments.',
        'supervisor' => 'View leave schedules and pending requests for your assigned employees.',
        default => 'View your submitted and approved leave schedules.',
    };
    $config = [
        'initialToday' => $today,
        'eventsUrl' => route($prefix.'.calendar.events', absolute: false),
        'role' => $role,
        'statuses' => $statuses,
        'filterNames' => ['status', ...array_keys($filters), ...match ($role) {
            'hr' => ['employee', 'employee_id'], 'supervisor' => ['employee_id'], default => [],
        }],
    ];
@endphp

<x-layouts.app :title="$title">
    <x-page-header :title="$title" :description="$description">
        <x-button variant="secondary" :href="route($prefix.'.index')">{{ $role === 'hr' ? 'Leave Monitoring' : ($role === 'supervisor' ? 'Leave Management' : 'My Leave') }}</x-button>
        @if($role === 'employee')
            <x-button :href="route('employee.leave.create')">Apply for Leave</x-button>
        @endif
    </x-page-header>

    <div class="space-y-4" data-leave-calendar x-data="leaveCalendar(@js($config))"
         @workspace:leave-calendar.window="schedule()">
        <section class="card" aria-label="Calendar filters">
            <div class="flex flex-wrap items-end gap-x-4 gap-y-3 px-4 py-3">
                @foreach($filters as $key => [$label, $options])
                    <label class="min-w-0 flex-[1_1_10rem] text-sm font-semibold">
                        {{ $label }}
                        <select class="field mt-1" x-model="filters.{{ $key }}" @change="change()">
                            <option value="">All</option>
                            @foreach($options as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                        </select>
                    </label>
                @endforeach
                @if($role === 'hr')
                    <label class="min-w-0 flex-[1_1_11rem] text-sm font-semibold">
                        Employee
                        <input class="field mt-1" type="search" placeholder="Name or employee number" maxlength="100"
                               x-model.trim="filters.employee" @input="filters.employee_id = ''; change(350)">
                    </label>
                @elseif($role === 'supervisor')
                    <label class="min-w-0 flex-[1_1_12rem] text-sm font-semibold">
                        Employee
                        <select class="field mt-1" x-model="filters.employee_id" @change="change()">
                            <option value="">All assigned employees</option>
                            <template x-for="employee in team" :key="employee.id">
                                <option :value="String(employee.id)" x-text="employee.name"></option>
                            </template>
                        </select>
                    </label>
                @endif
                <label class="min-w-0 flex-[1_1_11rem] text-sm font-semibold">
                    Status
                    <select class="field mt-1" x-model="filters.status" @change="change()">
                        <option value="">Active leave</option>
                        @foreach($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <button type="button" class="text-link min-h-10 px-2 text-sm" @click="clearFilters()">Clear filters</button>
            </div>
        </section>

        @if($role === 'hr')
            <dl x-show="staffing" x-cloak class="grid grid-cols-2 gap-2 xl:grid-cols-4" aria-label="Leave totals">
                @foreach(['approved_today' => 'Employees on approved leave today', 'pending_requests' => 'Pending requests in view', 'scheduled' => 'Employees on approved leave in view', 'awaiting_requests' => 'Requests awaiting assignment in view'] as $count => $label)
                    <div class="rounded-lg border border-slate-200 bg-white px-3 py-2.5">
                        <dt class="text-xs text-slate-600">{{ $label }}</dt>
                        <dd class="mt-1 text-lg font-bold text-[#2F1B72]" x-text="staffing?.{{ $count }}"></dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <section class="card min-w-0" aria-label="Leave schedules">
            <header class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-4 py-3">
                <h2 class="min-w-0 text-base font-bold text-[#2F1B72]" x-text="heading" aria-live="polite"></h2>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-1" role="group" aria-label="Calendar navigation">
                        <button type="button" class="icon-button" aria-label="Previous period" @click="navigate(-1)"><x-icon name="chevron" class="rotate-180" /></button>
                        <button type="button" class="text-link min-h-10 px-2 text-sm" @click="selectPeriod(today)">Today</button>
                        <button type="button" class="icon-button" aria-label="Next period" @click="navigate(1)"><x-icon name="chevron" /></button>
                    </div>
                    <div class="flex rounded-lg border border-slate-200 p-0.5" role="group" aria-label="Calendar view">
                        @foreach(['month' => 'Month', 'week' => 'Week', 'list' => 'List'] as $value => $label)
                            <button type="button" class="min-h-9 rounded-md px-2.5 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-[#2F1B72]"
                                    :class="view === '{{ $value }}' ? 'bg-[#2F1B72] text-white' : 'text-slate-700 hover:bg-slate-100'"
                                    :aria-pressed="view === '{{ $value }}'" @click="changeView('{{ $value }}')">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </header>

            <div class="flex flex-wrap gap-2 border-b border-slate-200 px-4 py-2 text-xs" aria-label="Calendar legend">
                <template x-for="status in legend" :key="status">
                    <span class="rounded-full px-2 py-0.5 font-semibold" :class="statusColor(status)" x-text="statuses[status]"></span>
                </template>
            </div>

            <p x-show="loading" x-cloak class="px-4 py-3 text-sm text-slate-600" role="status">Loading leave schedules…</p>
            <div x-show="error" x-cloak class="flex flex-wrap items-center gap-3 px-4 py-3 text-sm" role="status">
                <span>Unable to load leave schedules.</span>
                <button type="button" class="text-link" @click="load(events.length > 0 && !!cursor)">Retry</button>
            </div>
            <div x-show="!loading && !error && !events.length" x-cloak class="px-4 py-3 text-sm" role="status">
                <strong>No leave applications found</strong>
                <p class="text-slate-600">No leave requests match the selected dates and filters.</p>
            </div>

            <div x-show="view !== 'list'" x-cloak>
                <div class="grid grid-cols-7 bg-slate-50 text-center text-xs font-semibold text-slate-600">
                    @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $day)
                        <div class="py-2">{{ $day }}</div>
                    @endforeach
                </div>
                <div class="grid grid-cols-7 border-l border-slate-200" aria-label="Calendar days">
                    <template x-for="day in days" :key="day">
                        <div class="min-w-0 border-r border-b border-slate-200 px-0.5 pb-1 sm:px-1"
                             :class="[selectedDay === day ? 'bg-[#F0ECF8]' : view === 'month' && day.slice(0, 7) !== anchor.slice(0, 7) ? 'bg-slate-50' : 'bg-white', view === 'week' ? 'min-h-18 sm:min-h-36' : 'min-h-14 sm:min-h-24']">
                            <button type="button" class="flex min-h-8 w-full items-center justify-between gap-1 rounded px-1 text-left text-xs hover:bg-[#F0ECF8] focus-visible:outline-2 focus-visible:outline-[#2F1B72]"
                                    :aria-label="format(day) + ', ' + (byDay[day]?.length || 0) + ' leave requests' + (staffing?.days[day]?.overlaps.length ? ', overlaps' : '')"
                                    :aria-current="day === today ? 'date' : null" :aria-pressed="selectedDay === day" @click="selectedDay = day">
                                <span :class="day === today ? 'font-bold text-[#087C68]' : 'font-semibold text-slate-700'" x-text="dayNumber(day)"></span>
                                <span x-show="staffing?.days[day]?.overlaps.length" class="text-amber-900" aria-hidden="true">!</span>
                            </button>
                            <button type="button" class="min-h-5 w-full rounded text-center text-xs font-semibold text-[#2F1B72] sm:hidden"
                                    x-show="byDay[day]?.length" @click="selectedDay = day"
                                    :aria-label="'Show leave for ' + format(day)" x-text="byDay[day]?.length"></button>
                            <div class="hidden space-y-1 sm:block">
                                <template x-for="entry in (byDay[day] || []).slice(0, 2)" :key="entry.id">
                                    <button type="button" class="w-full min-w-0 rounded px-1.5 py-0.5 text-left text-xs leading-tight focus-visible:outline-2 focus-visible:outline-offset-1 focus-visible:outline-[#2F1B72]"
                                            :class="statusColor(entry.status)" @click="openDetails(entry.id)"
                                            :title="entry.title + ' · ' + statuses[entry.status] + (entry.half_day ? ' · ' + halfDay(entry) : '')">
                                        <span class="block truncate font-semibold" x-text="entry.title"></span>
                                        <span class="block truncate" x-text="shortStatus(entry.status) + (entry.half_day ? ' · ' + entry.half_day.toUpperCase() : '')"></span>
                                    </button>
                                </template>
                                <button type="button" class="text-link block w-full px-1.5 text-left text-xs" x-show="(byDay[day]?.length || 0) > 2"
                                        @click="selectedDay = day; $nextTick(() => $refs.dayDetails.focus())"
                                        :aria-label="'Show all leave for ' + format(day)" x-text="'+' + ((byDay[day]?.length || 0) - 2) + ' more'"></button>
                            </div>
                        </div>
                    </template>
                </div>

                <section x-ref="dayDetails" tabindex="-1" class="space-y-2 px-4 py-3" aria-label="Selected day leave">
                    <h3 class="text-sm font-bold text-[#2F1B72]" x-text="format(selectedDay)"></h3>
                    <p x-show="!loading && !error && !selectedEvents.length" class="text-sm text-slate-600">No matching leave on this date.</p>
                    <template x-for="entry in selectedEvents" :key="entry.id">
                        <x-leave-calendar-event />
                    </template>
                </section>
            </div>

            <div x-show="view === 'list'" x-cloak class="space-y-2 px-4 py-3" aria-label="Leave schedule list">
                <template x-for="entry in events" :key="entry.id">
                    <x-leave-calendar-event />
                </template>
            </div>
            <div x-show="cursor" x-cloak class="border-t border-slate-200 px-4 py-2">
                <button type="button" class="text-link min-h-9 text-sm" :disabled="loading" @click="load(true)">Load more schedules</button>
            </div>
        </section>

        @if($role !== 'employee')
            <section class="card" x-show="staffing" x-cloak aria-label="Leave staffing summary">
                <div class="card-heading flex-wrap gap-3">
                    <h2>Leave staffing summary</h2>
                    <label class="text-sm">Summary date
                        <input class="field mt-1" type="date" :value="selectedDay" :min="range.from" :max="range.to"
                               @change="if (days.includes($event.target.value)) selectedDay = $event.target.value">
                    </label>
                </div>
                <div class="card-body">
                    <dl class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                        <div><dt class="text-xs text-slate-600">{{ $role === 'supervisor' ? 'Assigned active employees' : 'Active employees in scope' }}</dt><dd class="font-bold" x-text="staffing?.employees"></dd></div>
                        <div><dt class="text-xs text-slate-600">Employees on approved leave</dt><dd class="font-bold" x-text="dayStaffing.approved"></dd><p class="text-xs text-slate-600" x-show="dayStaffing.approved_half_day" x-text="dayStaffing.approved_half_day + ' on half-day leave'"></p></div>
                        <div><dt class="text-xs text-slate-600">Employees with pending leave</dt><dd class="font-bold" x-text="dayStaffing.pending"></dd></div>
                        <div><dt class="text-xs text-slate-600">Employees without approved leave</dt><dd class="font-bold" x-text="(staffing?.employees || 0) - dayStaffing.approved"></dd></div>
                    </dl>
                    <p class="mt-3 text-xs text-slate-600">Counts use all active leave types and statuses for the selected team, independent of event display filters. Work schedules and holidays are outside these counts.</p>
                    <div class="mt-3 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-950" x-show="overlapMessages.length">
                        <strong>Overlapping Leave Requests</strong>
                        <ul class="mt-1 list-disc pl-5">
                            <template x-for="message in overlapMessages" :key="message"><li x-text="message"></li></template>
                        </ul>
                    </div>
                </div>
            </section>
        @endif

        <div class="modal-overlay" x-show="detailId !== null" x-cloak x-trap.inert.noscroll="detailId !== null"
             @click.self="closeDetails()" @keydown.escape.window="if (detailId !== null) closeDetails()">
            <section class="modal max-h-[85dvh] overflow-y-auto" role="dialog" aria-modal="true" aria-labelledby="calendar-detail-title">
                <div class="flex items-center justify-between gap-3">
                    <h2 id="calendar-detail-title" class="font-bold text-[#2F1B72]">Leave application details</h2>
                    <button type="button" class="icon-button" @click="closeDetails()" aria-label="Close leave details"><x-icon name="close" /></button>
                </div>
                <p class="py-3 text-sm text-slate-600" x-show="!detail && !detailError" role="status">Loading leave details…</p>
                <div class="py-3 text-sm" x-show="detailError" role="status">
                    <p>Unable to load leave details.</p>
                    <button type="button" class="text-link mt-2" @click="fetchDetails(detailId)">Retry</button>
                </div>
                <template x-if="detail">
                    <div class="mt-3">
                        <p class="break-words font-semibold" x-text="detail.title"></p>
                        <dl class="my-3 text-sm">
                            <template x-for="[label, value] in Object.entries({
                                'Employee number': detail.employee_number, Department: detail.department, Branch: detail.branch,
                                'Leave type': role === 'employee' ? null : detail.type, 'Inclusive dates': dates(detail.start, detail.end),
                                'Requested days': detail.duration, Session: halfDay(detail), Status: statuses[detail.status],
                                Supervisor: detail.supervisor, Submitted: detail.submitted, Decision: detail.decision, Remarks: detail.remarks,
                            })" :key="label">
                                <div x-show="value" class="profile-row gap-2"><dt class="text-slate-600" x-text="label"></dt><dd class="min-w-0 break-words" x-text="value"></dd></div>
                            </template>
                        </dl>
                        <div class="flex flex-wrap gap-2">
                            <a class="button" :href="detail.url" x-text="detailLinkLabel"></a>
                            <a class="button secondary" x-show="detail.pdf_url" :href="detail.pdf_url">View Approved Form</a>
                        </div>
                    </div>
                </template>
            </section>
        </div>
    </div>
</x-layouts.app>
