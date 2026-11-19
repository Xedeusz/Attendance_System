@props(['type'])
@php
    $tone = match ($type) {
        'special_non_working' => 'teal',
        'special_working' => 'blue',
        'local' => 'amber',
        'company' => 'neutral',
        default => 'purple',
    };
@endphp
<x-badge :tone="$tone" :class="match($tone) { 'purple' => 'bg-violet-100! text-violet-900!', 'amber' => 'bg-amber-100! text-amber-900!', default => '' }">{{ \App\Models\AttendanceHoliday::TYPES[$type] }}</x-badge>
