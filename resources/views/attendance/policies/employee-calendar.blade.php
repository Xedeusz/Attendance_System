@php
    $holidayTags = [
        'regular' => ['Holiday', 'flag', 'bg-amber-100 text-amber-900'],
        'special_non_working' => ['Special holiday', 'calendar', 'bg-amber-100 text-amber-900'],
        'special_working' => ['Special working', 'calendar', 'bg-blue-100 text-blue-900'],
        'local' => ['Local holiday', 'pin', 'bg-amber-100 text-amber-900'],
        'company' => ['Company holiday', 'building', 'bg-violet-100 text-violet-900'],
    ];
    $legend = [
        ['Work', 'briefcase', 'bg-teal-100 text-teal-900'],
        ['Rest', 'moon', 'bg-slate-100 text-slate-700'],
        ['Approved leave', 'check', 'bg-violet-100 text-violet-900'],
        ['Holiday', 'flag', 'bg-amber-100 text-amber-900'],
        ['Special working', 'calendar', 'bg-blue-100 text-blue-900'],
        ['Company holiday', 'building', 'bg-violet-100 text-violet-900'],
    ];
    $leavePeriodLabels = \App\Models\LeaveRequest::PERIODS;
    $leavePeriodTones = [
        'upcoming' => 'bg-blue-100 text-blue-900',
        'ongoing' => 'bg-teal-100 text-teal-900',
        'completed' => 'bg-slate-100 text-slate-700',
    ];
    $dayLink = fn ($day) => route('employee.work-calendar', ['month' => $month->format('Y-m'), 'day' => $day->toDateString()]).'#work-date-details';
    $last = $month->endOfMonth()->startOfDay();
@endphp
<x-layouts.app title="My Work Calendar">
    <x-page-header title="My Work Calendar" description="View your work schedule, approved leave, and holidays.">
        <x-button variant="secondary" :href="route('employee.attendance.history')">Attendance history</x-button>
    </x-page-header>
    <x-form-errors />
    <div x-data="workCalendar" class="space-y-4">
        <section class="card" aria-labelledby="work-calendar-month">
            <header class="card-heading gap-3 px-3! py-3! sm:px-5!">
                <h2 id="work-calendar-month" class="text-lg">{{ $month->format('F Y') }}</h2>
                <nav class="flex flex-wrap items-center gap-2" aria-label="Month navigation">
                    <a class="icon-button" aria-label="Previous month" href="{{ route('employee.work-calendar', ['month' => $month->subMonth()->format('Y-m')]) }}"><x-icon name="chevron" class="rotate-180" /></a>
                    <x-button variant="secondary" :href="route('employee.work-calendar', ['month' => $today->format('Y-m')])">Today</x-button>
                    <a class="icon-button" aria-label="Next month" href="{{ route('employee.work-calendar', ['month' => $month->addMonth()->format('Y-m')]) }}"><x-icon name="chevron" /></a>
                </nav>
            </header>
            <div class="calendar-tools">
                <ul class="flex flex-wrap gap-1.5" aria-label="Calendar legend">
                    @foreach($legend as [$label, $icon, $tone])
                        <li class="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold {{ $tone }}"><x-icon :name="$icon" class="size-3.5!" />{{ $label }}</li>
                    @endforeach
                </ul>
                <div class="calendar-controls">
                    <form method="GET" class="flex flex-wrap items-center gap-2">
                        <label class="sr-only" for="work-month">Calendar month</label>
                        <input class="field w-auto!" id="work-month" name="month" type="month" value="{{ $month->format('Y-m') }}">
                        <button type="submit" class="text-link">Go to month</button>
                    </form>
                    @if($dates)
                        <details data-preserve-open>
                            <summary class="text-link cursor-pointer">Filters</summary>
                            <fieldset class="mt-2 flex flex-wrap gap-3 text-sm">
                                <legend class="sr-only">Visible calendar events</legend>
                                @foreach(['work' => 'Work schedule', 'rest' => 'Rest days', 'holidays' => 'Holidays', 'leave' => 'Approved leave'] as $key => $label)
                                    <label class="flex items-center gap-1"><input type="checkbox" checked x-model="filters.{{ $key }}"> {{ $label }}</label>
                                @endforeach
                                <button type="button" class="text-link" @click="reset()">Reset filters</button>
                            </fieldset>
                        </details>
                    @endif
                </div>
            </div>
            @if($dates)
                <div class="grid grid-cols-7 bg-slate-50 text-center text-xs font-semibold text-slate-600">
                    @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                        <span class="py-2">{{ $weekday }}</span>
                    @endforeach
                </div>
                @for($week = $month->startOfWeek(); $week->lte($last); $week = $week->addWeek())
                    @php
                        $weekEnd = $week->addDays(6);
                    @endphp
                    <div class="border-t border-slate-200">
                        <div class="grid grid-cols-7 divide-x divide-slate-200">
                            @for($day = $week; $day->lte($weekEnd); $day = $day->addDay())
                                @php
                                    $policy = $dates[$day->toDateString()] ?? null;
                                @endphp
                                <div class="min-w-0">
                                    @if($policy)
                                        @php
                                            $isToday = $day->isSameDay($today);
                                            $holiday = $policy['holidays'][0] ?? null;
                                            $isRest = $policy['obligation'] === 'not_scheduled' && !$holiday;
                                        @endphp
                                        <a href="{{ $dayLink($day) }}" @click="openDate($event)" @if($isToday) aria-current="date" @endif
                                           class="block min-h-14 rounded-sm px-1 py-1.5 text-xs hover:bg-slate-50 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-violet-800 sm:px-2 {{ $isToday ? 'ring-2 ring-inset ring-violet-800' : '' }}">
                                            <time datetime="{{ $day->toDateString() }}" class="inline-flex size-6 items-center justify-center rounded-full font-bold {{ $isToday ? 'bg-(--purple-action) text-white' : '' }}"><span aria-hidden="true">{{ $day->day }}</span><span class="sr-only">{{ $day->format('l, F j, Y') }}</span></time>
                                            @if($holiday)
                                                @php
                                                    [$label, $icon, $tone] = $holidayTags[$holiday['type']];
                                                @endphp
                                                <span x-show="filters.holidays" class="mt-1 flex items-center gap-1 rounded px-1 py-0.5 font-bold {{ $tone }}" title="{{ $holiday['name'] }} · {{ $label }}">
                                                    <x-icon :name="$icon" class="size-3.5! shrink-0" />
                                                    <span class="sr-only sm:not-sr-only sm:truncate">{{ $holiday['name'] }}</span>
                                                    <span class="sr-only">{{ \App\Models\AttendanceHoliday::TYPES[$holiday['type']] }}</span>
                                                    @if(count($policy['holidays']) > 1)<span class="hidden shrink-0 md:inline">+{{ count($policy['holidays']) - 1 }}</span>@endif
                                                </span>
                                            @endif
                                            @if($policy['obligation'] === 'work')
                                                <span x-show="filters.work" class="mt-1 inline-flex items-center gap-1 rounded bg-teal-100 px-1 py-0.5 font-semibold text-teal-900"><x-icon name="briefcase" class="size-3.5!" /><span class="sr-only sm:not-sr-only">Work</span></span>
                                            @elseif($isRest)
                                                <span x-show="filters.rest" class="mt-1 inline-flex items-center gap-1 rounded bg-slate-100 px-1 py-0.5 font-semibold text-slate-700"><x-icon name="moon" class="size-3.5!" /><span class="sr-only sm:not-sr-only">Rest</span></span>
                                            @elseif(in_array($policy['obligation'], ['no_schedule', 'excused'], true))
                                                <span x-show="filters.work" class="mt-1 inline-flex items-center gap-1 rounded bg-slate-100 px-1 py-0.5 text-slate-700"><x-icon name="info" class="size-3.5!" /><span class="sr-only sm:not-sr-only">{{ $policy['obligation'] === 'excused' ? 'Excused' : 'No schedule' }}</span></span>
                                            @endif
                                        </a>
                                    @else
                                        <div class="h-full bg-slate-50" aria-hidden="true"></div>
                                    @endif
                                </div>
                            @endfor
                        </div>
                        <div x-show="filters.leave" class="grid grid-cols-7 gap-y-1">
                            @foreach($leaves as $leave)
                                @php
                                    $leaveStart = $leave->start_date->toDateString();
                                    $leaveEnd = $leave->end_date->toDateString();
                                    $from = max($week, $month, \Carbon\CarbonImmutable::parse($leaveStart, $month->timezone));
                                    $through = min($weekEnd, $last, \Carbon\CarbonImmutable::parse($leaveEnd, $month->timezone));
                                @endphp
                                @continue($from->gt($through))
                                @php($period = $leave->periodStatus())
                                <a href="{{ $dayLink($from) }}" @click="openDate($event)" data-leave-segment="{{ $leave->id }}"
                                   style="grid-column: {{ $from->dayOfWeekIso }} / {{ $through->dayOfWeekIso + 1 }}"
                                   class="my-0.5 flex min-w-0 items-center gap-1 overflow-hidden px-1.5 py-1 text-xs font-semibold hover:opacity-80 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-violet-800 {{ $leavePeriodTones[$period] }} {{ $from->toDateString() === $leaveStart ? 'ml-1 rounded-l-md' : 'border-l-2 border-slate-300' }} {{ $through->toDateString() === $leaveEnd ? 'mr-1 rounded-r-md' : 'border-r-2 border-slate-300' }}" title="Leave · {{ $leavePeriodLabels[$period] }}">
                                    <x-icon name="check" class="size-3.5! shrink-0" />
                                    <span class="truncate" aria-hidden="true">Leave · {{ $leavePeriodLabels[$period] }}{{ $leave->half_day ? ' · '.strtoupper($leave->half_day) : '' }}</span>
                                    <span class="sr-only">Approved leave, {{ $from->format('M j') }} through {{ $through->format('M j') }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endfor
            @else
                <div class="card-body"><x-empty-state title="Employee profile required" description="Contact HR to link your profile to see your work calendar." icon="calendar" /></div>
            @endif
        </section>
        @if($details)
            <section id="work-date-details" class="card card-body">@include('attendance.policies.employee-day', $details)</section>
        @endif
        <x-modal id="work-date" title="Date details">
            <div class="mt-4" x-html="details"></div>
        </x-modal>
    </div>
</x-layouts.app>
