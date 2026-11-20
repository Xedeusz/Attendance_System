@php
    $title = $schedule->exists ? 'Work schedule details' : 'New work schedule';
    $scope = $schedule->employee_id ? 'employee' : ($schedule->department_id ? 'department' : ($schedule->branch_id ? 'branch' : 'company'));
    $clock = fn ($value) => $value ? substr($value, 0, 5) : null;
@endphp
<x-layouts.app :title="$title">
    <x-page-header :title="$title" description="Configure the shift, break, grace period, and assignment.">
        <x-button variant="secondary" :href="route('hr.schedules.index')">Schedules &amp; Policies</x-button>
    </x-page-header>
    <section class="card">
        <form method="POST" action="{{ $schedule->exists ? route('hr.schedules.update', $schedule) : route('hr.schedules.store') }}"
              class="card-body space-y-5"
              x-data="{ scope: @js(old('scope', $scope)), start: @js(old('starts_at', $clock($schedule->starts_at))), end: @js(old('ends_at', $clock($schedule->ends_at))), breakStart: @js(old('break_starts_at', $clock($schedule->break_starts_at))), breakEnd: @js(old('break_ends_at', $clock($schedule->break_ends_at))), paid: @js((string)old('paid_break', (int)$schedule->paid_break)), offset(value) { if (!this.start || !value) return 0; const mins = time => Number(time.slice(0,2))*60 + Number(time.slice(3,5)); return (mins(value)-mins(this.start)+1440)%1440 }, unpaid() { if (!this.breakStart || !this.breakEnd || this.paid === '1') return 0; return Math.max(0, Math.min(this.offset(this.end), this.offset(this.breakEnd)) - Math.max(0, this.offset(this.breakStart))) } }">
            @csrf
            @if($schedule->exists) @method('PUT') @endif
            <x-form-errors />
            @if($schedule->exists)
                <p class="small muted">Version #{{ $schedule->id }} applies from {{ $schedule->effective_from->toDateString() }} through {{ $schedule->effective_until?->toDateString() ?? 'an open end date' }}. Saving creates a new version for this assignment.</p>
            @endif
            <fieldset class="record-fieldset">
                <legend>Shift and break</legend>
                <div class="grid gap-x-5 sm:grid-cols-2">
                <x-input name="name" label="Schedule name" :value="$schedule->name" maxlength="150" required />
                <x-input name="description" label="Description" :value="$schedule->description" maxlength="2000" />
                <x-input name="starts_at" label="Work start" type="time" :value="$clock($schedule->starts_at)" x-model="start" required />
                <x-input name="ends_at" label="Work end" type="time" :value="$clock($schedule->ends_at)" x-model="end" required />
                <x-input name="break_starts_at" label="Break start" type="time" :value="$clock($schedule->break_starts_at)" x-model="breakStart" />
                <x-input name="break_ends_at" label="Break end" type="time" :value="$clock($schedule->break_ends_at)" x-model="breakEnd" />
                <x-select name="paid_break" label="Break classification" :options="[0 => 'Unpaid', 1 => 'Paid']" :value="(int)$schedule->paid_break" :placeholder="null" x-model="paid" />
                <x-input name="grace_minutes" label="Grace period (minutes)" type="number" min="0" max="1439" :value="$schedule->grace_minutes ?? 0" required />
                <x-select name="grace_method" label="Grace computation" :options="\App\Models\WorkSchedule::GRACE_METHODS" :value="$schedule->grace_method" required />
                </div>
            </fieldset>
            <dl class="grid gap-2 rounded-lg bg-slate-50 p-4 text-sm sm:grid-cols-2 xl:grid-cols-4" aria-label="Schedule duration preview" aria-live="polite">
                <div><dt>Scheduled presence (min)</dt><dd class="font-semibold" x-text="Math.max(0, offset(end))"></dd></div>
                <div><dt>Break duration (min)</dt><dd class="font-semibold" x-text="breakStart && breakEnd ? Math.max(0, offset(breakEnd) - offset(breakStart)) : 0"></dd></div>
                <div><dt>Unpaid break (min)</dt><dd class="font-semibold" x-text="unpaid()"></dd></div>
                <div><dt>Expected net work (min)</dt><dd class="font-semibold" x-text="Math.max(0, offset(end) - unpaid())"></dd></div>
            </dl>
            <p class="small muted">An end time earlier than the start time ends the following day. Select working days by the date the shift starts. Example only: 08:00–16:00 with a 12:00–13:00 unpaid break is 420 working minutes.</p>
            <fieldset>
                <legend class="field-label">Working days</legend>
                <div class="flex flex-wrap gap-4">
                    @foreach(['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'] as $index => $label)
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="working_days[]" value="{{ $index + 1 }}" @checked(in_array($index + 1, (array) old('working_days', $schedule->working_days ?? [])))> {{ $label }}</label>
                    @endforeach
                </div>
            </fieldset>
            <fieldset class="record-fieldset">
                <legend>Assignment and effective dates</legend>
                <div class="grid gap-x-5 sm:grid-cols-2">
                <x-input name="effective_from" :label="$schedule->exists ? 'New version effective from' : 'Effective from'" type="date" required />
                <x-input name="effective_until" label="Effective through (optional)" type="date" :value="$schedule->effective_until?->toDateString()" />
                <x-select name="scope" label="Assign to" :options="['company'=>'Company default', 'employee'=>'Employee', 'department'=>'Department', 'branch'=>'Branch']" :value="$scope" :placeholder="null" x-model="scope" />
                @foreach(['employee' => $employees, 'department' => $departments, 'branch' => $branches] as $assignment => $options)
                    <div x-show="scope === '{{ $assignment }}'">
                        @if($schedule->exists)
                            <x-select :name="$assignment.'_id'" :label="ucfirst($assignment)" :options="$options" :value="$schedule->{$assignment.'_id'}" />
                        @else
                            <label class="field-label" for="{{ $assignment }}_ids">{{ ucfirst($assignment) }} assignments</label>
                            <select class="field min-h-28" multiple name="{{ $assignment }}_ids[]" id="{{ $assignment }}_ids" aria-describedby="schedule-target-help-{{ $assignment }}">
                                @foreach($options as $id => $label)
                                    <option value="{{ $id }}" @selected(in_array($id, (array) old($assignment.'_ids', [])))>{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="small muted" id="schedule-target-help-{{ $assignment }}">Choose one or more assignments. Each assignment receives its own version.</p>
                        @endif
                    </div>
                @endforeach
                </div>
            </fieldset>
            <x-button type="submit">{{ $schedule->exists ? 'Save new schedule version' : 'Save schedule' }}</x-button>
        </form>
    </section>
</x-layouts.app>
