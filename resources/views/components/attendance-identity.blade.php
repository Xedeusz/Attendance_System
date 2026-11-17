@props(['employee', 'branchName' => null])
@php($avatarUser = $employee->user_id === auth()->id() ? auth()->user() : $employee->user)
<div {{ $attributes->class(['attendance-identity']) }} aria-label="Employee identification">
<x-user-avatar :user="$avatarUser" class="attendance-identity-avatar" />
<div class="min-w-0"><h2 class="font-semibold break-words">{{ $employee->full_name }}</h2><p class="small muted break-words">Employee ID: {{ $employee->employee_number }}</p><p class="small muted break-words">{{ $branchName ?? 'No primary branch assigned' }}</p></div>
</div>
