@props(['leave'])
@if($period = $leave->periodStatus())
    <x-badge :tone="match ($period) { 'upcoming' => 'blue', 'ongoing' => 'teal', default => 'neutral' }">Leave period: {{ ucfirst($period) }}</x-badge>
@endif
