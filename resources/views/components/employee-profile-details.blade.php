@props(['employee', 'hr' => false, 'supervisors' => []])
<div class="card-body">
@foreach(['Work email' => $employee->work_email, 'Department' => $employee->department?->department_name ?? 'Unassigned', 'Position' => $employee->position_title ?? 'Not recorded', 'Primary branch' => $employee->currentAssignment?->branch?->branch_name ?? 'Unassigned', 'Employment status' => $employee->employment_status] as $label => $value)
<div class="profile-row gap-4"><span class="muted">{{ $label }}</span><span class="min-w-0 break-words">{{ $value }}</span></div>
@endforeach
@if($hr)
@foreach(['Sex' => $employee->sex ? ucfirst($employee->sex) : 'Requires HR completion', 'Contact number' => $employee->contact_number ?? 'Not provided', 'Date hired' => $employee->date_hired?->format('M j, Y') ?? 'Not recorded', 'Assigned Supervisor' => collect($supervisors)->pluck('name')->join(', ') ?: 'Unassigned'] as $label => $value)
<div class="profile-row gap-4"><span class="muted">{{ $label }}</span><span class="min-w-0 break-words">{{ $value }}</span></div>
@endforeach
@endif
</div>
