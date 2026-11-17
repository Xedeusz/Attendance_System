@props(['employee'])
<div class="card-body flex flex-wrap items-center gap-5">
<x-user-avatar :user="$employee->user" large />
<div class="min-w-0"><h2 class="text-xl font-semibold break-words">{{ $employee->full_name }}</h2><p class="small muted break-words">{{ $employee->employee_number }} &middot; {{ $employee->department?->department_name ?? 'Unassigned department' }}</p><p class="small muted">{{ $employee->currentAssignment?->branch?->branch_name ?? 'Unassigned branch' }}</p><x-state :value="$employee->employment_status" /></div>
</div>
