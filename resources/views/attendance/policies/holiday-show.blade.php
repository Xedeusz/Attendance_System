<x-layouts.app :title="$holiday->name">
    <x-page-header :title="$holiday->name" :description="$holiday->holiday_date->format('F j, Y')">
        <x-button :href="route('hr.holidays.edit', $holiday)">Edit holiday</x-button>
        <x-button variant="secondary" :href="route('hr.holidays.index', ['year' => $holiday->holiday_date->year])">Holiday Management</x-button>
    </x-page-header>
    <section class="card mb-6">
        <div class="card-heading"><h2>Holiday details</h2><x-attendance-holiday-badge :type="$holiday->type" /></div>
        <dl class="card-body">
            @foreach([
                'Status' => \App\Models\AttendanceHoliday::STATUSES[$holiday->status],
                'Coverage' => $holiday->coverageLabel(),
                'Normal work expectation' => $holiday->work_expected ? 'Follow assigned schedule' : 'Non-working unless shift approved',
                'Description' => $holiday->description,
                'Official declaration' => $holiday->declaration,
                'Proclamation number' => $holiday->proclamation_number,
                'Import batch' => $holiday->holiday_import_id,
            ] as $label => $value)
                <div class="profile-row"><dt>{{ $label }}</dt><dd>{{ $value ?? '—' }}</dd></div>
            @endforeach
            @if($holiday->source_url)<div class="profile-row"><dt>Source</dt><dd class="wrap-anywhere"><a class="text-link" href="{{ $holiday->source_url }}">{{ $holiday->source_url }}</a></dd></div>@endif
        </dl>
    </section>
</x-layouts.app>
