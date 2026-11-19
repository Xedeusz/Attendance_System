@props(['calculation', 'policy' => null, 'compact' => false, 'expected' => null])
@php
    $policy ??= $calculation?->policy;
    $schedule = $policy['schedule'] ?? null;
    $minutes = fn ($value) => $value === null ? '—' : ($value < 60 ? $value.'m' : intdiv($value, 60).'h '.($value % 60).'m');
@endphp
@if($policy)
    <div class="space-y-2">
        <p class="font-semibold">{{ \App\Services\AttendancePolicyResolver::OBLIGATIONS[$policy['obligation']] }}</p>
        @if($schedule)
            <p class="small">
                {{ $schedule['name'] }} · {{ substr($schedule['starts_at'], 0, 5) }}–{{ substr($policy['adjusted_end'] ?? $schedule['ends_at'], 0, 5) }}
                @if(($policy['adjusted_end'] ?? $schedule['ends_at']) < $schedule['starts_at']) (next day) @endif
                @unless($compact) · Version #{{ $schedule['id'] }} · {{ $policy['timezone'] }} @endunless
            </p>
        @endif
        @foreach($policy['holidays'] as $holiday)
            <p class="small">{{ $holiday['name'] }} <x-attendance-holiday-badge :type="$holiday['type']" /></p>
        @endforeach
        @if($policy['exception'])
            <p class="small">{{ \App\Models\WorkScheduleException::KINDS[$policy['exception']['kind']] }}</p>
        @endif
        @if($policy['partial_leave'])
            <p class="small muted">Approved {{ strtoupper($policy['partial_leave']) }} leave. Partial-day hours require an explicit policy.</p>
        @endif
        @if($calculation)
            @if($calculation->incomplete)<x-badge tone="warning">Incomplete · Time Out required</x-badge>@endif
            @if($compact)
                <p class="small">Expected {{ $minutes($calculation->expected_minutes) }} · Net {{ $minutes($calculation->net_minutes) }} · Late {{ $minutes($calculation->late_minutes) }} · Undertime {{ $minutes($calculation->undertime_minutes) }}</p>
            @else
                <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach([
                        'Recorded attendance' => $calculation->recorded_minutes, 'Policy-based unpaid break' => $calculation->unpaid_break_minutes,
                        'Net recorded work' => $calculation->net_minutes, 'Expected net work' => $calculation->expected_minutes,
                        'Late' => $calculation->late_minutes, 'Undertime' => $calculation->undertime_minutes,
                    ] as $label => $value)
                        <div><dt class="small muted">{{ $label }}</dt><dd class="font-semibold">{{ $minutes($value) }}</dd></div>
                    @endforeach
                </dl>
            @endif
        @elseif($expected !== null)
            <p class="small">Expected net work {{ $minutes($expected) }}</p>
        @endif
    </div>
@else
    <p class="small muted">No saved computation.</p>
@endif
