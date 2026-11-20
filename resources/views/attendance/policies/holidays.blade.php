<x-layouts.app title="Holiday Management">
    <x-page-header title="Holiday Management" description="Manage government declarations and LIMCOMA holidays across all branches.">
        <x-button :href="route('hr.holidays.create')">Add holiday</x-button>
        <x-button variant="secondary" :href="route('hr.holiday-import.index')">Import Philippine holidays</x-button>
    </x-page-header>
    <x-form-errors />
    <section class="card">
        <form class="toolbar" method="GET" aria-label="Holiday filters">
            <x-input name="year" label="Year" type="number" min="1900" max="2200" :value="$year" />
            <x-select name="view" label="View" :options="['list' => 'Annual list', 'month' => 'Monthly calendar']" :value="$monthly ? 'month' : 'list'" :placeholder="null" />
            <x-select name="month" label="Calendar month" :options="collect(range(1, 12))->mapWithKeys(fn ($number) => [$number => $month->month($number)->format('F')])" :value="$month->month" :placeholder="null" />
            <x-select name="type" label="Holiday classification" :options="\App\Models\AttendanceHoliday::TYPES" :value="request('type')" placeholder="All classifications" />
            <x-select name="branch_id" label="Branch" :options="$branches" :value="request('branch_id')" placeholder="All branches" />
            <x-button type="submit">Apply filters</x-button>
        </form>
        @if($monthly)
            <div class="card-heading">
                <h2>{{ $month->format('F Y') }}</h2>
                <div class="flex gap-3">
                    @foreach(['Previous' => $month->subMonth(), 'Next' => $month->addMonth()] as $label => $target)
                        <a class="text-link" href="{{ route('hr.holidays.index', [...request()->only('type', 'branch_id'), 'view' => 'month', 'year' => $target->year, 'month' => $target->month]) }}">{{ $label }} month</a>
                    @endforeach
                </div>
            </div>
            <x-work-calendar-grid :month="$month" day-view="attendance.policies.holiday-day" :day-data="['byDate' => $holidays->groupBy(fn ($holiday) => $holiday->holiday_date->toDateString())]" />
        @else
            <x-table :columns="['Date', 'Holiday', 'Classification', 'Coverage', 'Work expectation', 'Status']" caption="Annual holiday calendar">
                @forelse($holidays as $holiday)
                    <tr><td>{{ $holiday->holiday_date->format('M j, Y') }}</td>
                        <th scope="row"><a class="text-link" href="{{ route('hr.holidays.show', $holiday) }}">{{ $holiday->name }}</a></th>
                        <td><x-attendance-holiday-badge :type="$holiday->type" /></td>
                        <td>{{ $holiday->coverageLabel() }}</td>
                        <td>{{ $holiday->work_expected ? 'Follow assigned schedule' : 'Non-working unless shift approved' }}</td>
                        <td><x-state :value="$holiday->status" /></td>
                    </tr>
                @empty <tr><td colspan="6"><x-empty-state title="No holidays match this view" description="Change the filters or add a holiday." icon="calendar" /></td></tr> @endforelse
            </x-table>
        @endif
    </section>
</x-layouts.app>
