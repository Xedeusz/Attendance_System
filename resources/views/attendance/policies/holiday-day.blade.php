@foreach($byDate->get($date, []) as $holiday)
    <div class="mt-2 space-y-1">
        <a class="text-link wrap-anywhere" href="{{ route('hr.holidays.show', $holiday) }}">{{ $holiday->name }}</a>
        <div><x-attendance-holiday-badge :type="$holiday->type" /></div>
        @if($holiday->status !== 'active')<p class="small muted">{{ \App\Models\AttendanceHoliday::STATUSES[$holiday->status] }}</p>@endif
    </div>
@endforeach
