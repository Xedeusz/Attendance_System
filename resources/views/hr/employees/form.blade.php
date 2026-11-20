<x-layouts.app :title="$employee->exists ? 'Edit Employee' : 'Register New Employee'">
<x-page-header :title="$employee->exists ? 'Edit Employee' : 'Register New Employee'" description="Maintain accurate employment details. Account activation is completed by the employee." />
<form class="card" method="POST" action="{{ $employee->exists ? route('hr.employees.update', $employee) : route('hr.employees.store') }}">@csrf <x-edit-version :record="$employee" /> @if($employee->exists) @method('PUT') @endif
<div class="card-heading"><h2>Employee account</h2><x-state :value="$employee->exists ? $employee->user->account_status : 'pending_activation'" /></div>
<div class="card-body form-grid">
<x-input name="employee_number" label="Employee number" :value="$employee->employee_number" required maxlength="40" />
<x-input name="work_email" label="Work email" type="email" :value="$employee->exists ? $employee->work_email : ''" required maxlength="254" />
</div>
<div class="card-heading"><h2>Personal information</h2></div>
<div class="card-body form-grid">
<x-input name="first_name" label="First name" :value="$employee->first_name" required maxlength="100" /><x-input name="last_name" label="Last name" :value="$employee->last_name" required maxlength="100" />
<x-input name="middle_name" label="Middle name (optional)" :value="$employee->middle_name" maxlength="100" /><x-input name="suffix" label="Suffix (optional)" :value="$employee->suffix" maxlength="20" />
<x-input name="contact_number" label="Contact number (optional)" type="tel" :value="$employee->contact_number" maxlength="30" />
</div><div class="card-heading"><h2>Employment information</h2></div><div class="card-body form-grid">
<x-select name="branch_id" label="Assigned primary branch" :options="$branches->pluck('branch_name', 'id')" :value="$employee->currentAssignment?->branch_id" required />
<x-select name="department_id" label="Company-wide department" :options="$departments->pluck('department_name', 'id')" :value="$employee->department_id" required />
<x-input name="position_title" label="Position title" :value="$employee->position_title" required maxlength="150" />
<x-input name="date_hired" label="Date hired" type="date" :value="$employee->date_hired?->format('Y-m-d')" required />
<x-select name="employment_status" label="Employment status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$employee->employment_status ?? 'active'" :placeholder="null" required />
</div><div class="card-body border-t border-slate-100"><p class="small muted mb-5 leading-6">Only active branches and departments can be assigned. Transfers retain the previous assignment. Changing a work email requires activation at the new address; ordinary edits preserve the password.</p><div class="flex gap-3 flex-wrap"><x-button type="submit">{{ $employee->exists ? 'Save changes' : 'Save employee & issue invitation' }}</x-button><x-button :href="route('hr.employees.index')" variant="secondary">Cancel</x-button></div></div>
</form></x-layouts.app>
