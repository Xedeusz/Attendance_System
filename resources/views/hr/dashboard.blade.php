<x-layouts.app title="HR Dashboard">
    <x-page-header title="HR Dashboard" description="Daily HR Overview" />

    <div class="@container/hr-dashboard grid gap-5">
        @php($dateFilter = ['from' => $businessDate->toDateString(), 'to' => $businessDate->toDateString()])
        <section aria-label="Primary HR statistics">
            <dl class="grid auto-rows-fr gap-3 @min-[19rem]/hr-dashboard:grid-cols-2 @min-[52rem]/hr-dashboard:grid-cols-4">
                @foreach([
                    ['Active Employees', 'users', route('hr.employees.index', ['account_status' => 'active'])],
                    ['Present Today', 'check', route('hr.attendance.index', $dateFilter)],
                    ['On Approved Leave', 'calendar', route('hr.leave.index', ['status' => 'approved', ...$dateFilter])],
                    ['Pending HR Actions', 'file', '#hr-action-center'],
                ] as [$label, $icon, $url])
                    <div class="card relative grid min-w-0 content-start gap-3 p-4 hover:border-(--border-control) sm:p-5" data-metric="{{ $label }}">
                        <dt class="flex min-h-10 items-start gap-2 text-sm font-semibold leading-5 text-(--text-secondary) @min-[52rem]/hr-dashboard:min-h-0">
                            <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-(--limcoma-purple-light) text-(--limcoma-purple)" aria-hidden="true">
                                <x-icon :name="$icon" class="size-4" />
                            </span>
                            <a class="self-center wrap-anywhere after:absolute after:inset-0 after:rounded-[inherit] focus-visible:outline-none focus-visible:after:outline-2 focus-visible:after:outline-offset-2 focus-visible:after:outline-(--limcoma-purple)" href="{{ $url }}">{{ $label }}</a>
                        </dt>
                        <dd class="text-3xl leading-none font-bold tabular-nums text-(--text-primary)"><strong>{{ number_format($stats[$label]) }}</strong></dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <div class="grid items-start gap-5 @min-[52rem]/hr-dashboard:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <section class="card min-w-0" aria-labelledby="today-heading">
                <div class="card-heading">
                    <h2 id="today-heading">Today's Attendance</h2>
                    <time class="text-xs muted" datetime="{{ $businessDate->toDateString() }}">{{ $businessDate->format('D, M j, Y') }} <span class="whitespace-nowrap">· {{ config('attendance.timezone') }}</span></time>
                </div>
                <div class="card-body grid justify-items-start gap-4">
                    <dl class="grid w-full @min-[26rem]/hr-dashboard:grid-cols-3 @min-[26rem]/hr-dashboard:divide-x @min-[26rem]/hr-dashboard:divide-y-0 divide-y divide-(--border-color)">
                        @foreach($todayAttendance as $label => $count)
                            <div class="flex min-w-0 items-center justify-between gap-2 py-2 @min-[26rem]/hr-dashboard:flex-col @min-[26rem]/hr-dashboard:items-start @min-[26rem]/hr-dashboard:justify-start @min-[26rem]/hr-dashboard:px-3 @min-[26rem]/hr-dashboard:first:pl-0" data-metric="{{ $label }}">
                                <dt class="text-sm muted">{{ $label }}</dt>
                                <dd class="text-xl font-semibold tabular-nums text-(--text-primary)">{{ number_format($count) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <a class="text-link inline-flex min-h-11 items-center gap-2" href="{{ route('hr.attendance.index', $dateFilter) }}">View Attendance Monitoring <x-icon name="arrow" class="size-4" /></a>
                </div>
            </section>

            <section id="hr-action-center" tabindex="-1" class="card min-w-0 scroll-mt-20" aria-labelledby="hr-action-heading">
                <div class="card-heading"><h2 id="hr-action-heading">HR Action Center</h2></div>
                @if($actions)
                    <ul class="divide-y divide-(--border-color) px-4 sm:px-5">
                        @foreach($actions as $action)
                            <li>
                                <a class="flex min-h-14 items-center gap-3 rounded-lg px-2 py-2 text-(--text-primary) hover:bg-(--limcoma-purple-light) focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-(--limcoma-purple)" href="{{ $action['url'] }}">
                                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-900" aria-hidden="true"><x-icon :name="$action['icon']" class="size-4" /></span>
                                    <span class="min-w-0 flex-1 text-sm font-semibold wrap-anywhere">{{ $action['label'] }}</span>
                                    <x-badge tone="warning">{{ number_format($action['count']) }} <span class="sr-only">pending</span></x-badge>
                                    <x-icon name="chevron" class="size-4 shrink-0 text-(--limcoma-purple)" />
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="card-body flex items-start gap-3">
                        <span class="grid size-9 shrink-0 place-items-center rounded-lg bg-(--limcoma-teal-light) text-(--limcoma-teal-dark)" aria-hidden="true"><x-icon name="check" class="size-4" /></span>
                        <div><h3 class="font-semibold">All caught up!</h3><p class="mt-1 text-sm muted">No pending HR actions at the moment.</p></div>
                    </div>
                @endif
            </section>
        </div>
    </div>
</x-layouts.app>
