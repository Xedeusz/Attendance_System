@props(['employee', 'user'])

<section class="card workday-panel flex min-w-0 flex-col overflow-hidden" aria-labelledby="employee-id-heading">
    <header class="flex items-center gap-3 border-b-2 border-(--limcoma-teal) bg-(--limcoma-purple) px-4 py-3.5 text-white sm:px-5">
        <img src="{{ asset('LIMCOMA-LOGO.png') }}" alt="" width="40" height="40" class="size-10 shrink-0 rounded-lg bg-white object-contain p-0.5">
        <div class="min-w-0">
            <p class="text-sm font-bold leading-snug text-white">LIMCOMA Multipurpose Cooperative</p>
            <h2 id="employee-id-heading" class="workday-id-label mt-1 tracking-[0.14em] uppercase">Employee identification</h2>
        </div>
    </header>

    <div class="flex min-w-0 flex-1 flex-col gap-4 px-4 py-4 sm:px-5">
        <div class="flex min-w-0 items-center gap-4">
            <x-user-avatar :user="$user" class="workday-id-avatar ring-1 ring-(--limcoma-teal)/45 ring-offset-2" />
            <div class="min-w-0">
                <p class="wrap-anywhere text-[1.125rem] font-bold leading-tight text-(--text-primary) sm:text-xl">{{ $employee?->full_name ?: $user->name }}</p>
                @if(filled($employee?->employee_number))
                    <p class="mt-1 wrap-anywhere text-xs text-(--text-secondary)">Employee ID <span class="ml-1 font-semibold text-(--limcoma-purple)">{{ $employee->employee_number }}</span></p>
                @endif
            </div>
        </div>

        @if($employee)
            <dl class="grid min-w-0 grid-cols-2 gap-x-4 gap-y-3 border-t border-slate-200 pt-3">
                @foreach ([
                    'Assigned branch' => $employee->currentAssignment?->branch?->branch_name ?? 'Unassigned',
                    'Department' => $employee->department?->department_name,
                    'Position' => $employee->position_title,
                    'Employment status' => filled($employee->employment_status) ? ucfirst($employee->employment_status) : null,
                ] as $label => $value)
                    @continue(blank($value))
                    <div class="min-w-0">
                        <dt class="text-xs text-(--text-secondary)">{{ $label }}</dt>
                        <dd class="mt-0.5 wrap-anywhere text-sm font-semibold leading-snug text-(--text-primary)">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        @else
            <p class="small muted">No employee profile is linked to this login. Contact HR.</p>
        @endif

        <div class="mt-auto">
            <a class="text-link inline-flex min-h-8 items-center gap-1.5" href="{{ route('employee.profile') }}">View my profile <x-icon name="arrow" /></a>
        </div>
    </div>
</section>
