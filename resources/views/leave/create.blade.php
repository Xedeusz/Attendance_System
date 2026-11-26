<x-layouts.app title="Apply for Leave">
    <x-page-header title="Apply for Leave" description="Complete the LMPC leave application." />

    @php
        $today = now(config('attendance.timezone'))->toDateString();
        $leaveLabels = ['vacation' => 'Vacation Leave', 'sick' => 'Sick Leave', 'other' => 'Others (pls specify)'];
        $unavailableTypes = array_diff_key($leaveLabels, $leaveChoices->all());
        $allLeaveChoices = $leaveChoices->flatten(1);
        $annualLeaveChoices = $allLeaveChoices->where('balanceBased', true)->where('policyBasis', 'annual');
        $allAnnualCreditsUnavailable = $annualLeaveChoices->isNotEmpty() && $annualLeaveChoices->every(fn (array $choice): bool => ! $choice['selectable']);
    @endphp

    @if($types->isEmpty())
        <x-alert>No leave types are configured. Contact HR.</x-alert>
    @endif

    <section class="card mb-6 max-w-5xl" aria-labelledby="special-eligibility-heading">
        <div class="card-heading"><h2 id="special-eligibility-heading">Special Leave Eligibility</h2><x-button variant="secondary" :href="route('employee.leave.eligibility.create')">Request Eligibility Verification</x-button></div>
        <div class="card-body grid gap-3 sm:grid-cols-2">
            @forelse($allLeaveChoices->whereIn('policyBasis', ['event']) as $choice)
                @php
                    $verification = $eligibilityRequests->firstWhere('leave_type_id', (int) $choice['id']);
                @endphp
                <article class="rounded-md border border-slate-200 p-3"><div class="flex items-start justify-between gap-3"><h3 class="font-semibold">{{ $choice['name'] }}</h3><x-state :value="$verification?->status ?? 'not_requested'" /></div><p class="small muted mt-2">{{ $verification?->entitlement ? 'Verified entitlement: '.\App\Support\LeaveDays::withUnit($verification->entitlement->granted_days) : 'Entitlement not yet established' }}</p><div class="mt-3">@if($verification)<a class="text-link" href="{{ route('employee.leave.eligibility.show', $verification) }}">View verification request</a>@else<a class="text-link" href="{{ route('employee.leave.eligibility.create', ['leave_type_id' => $choice['id']]) }}">Request verification</a>@endif</div></article>
            @empty<p class="small muted">No event-based special leave policies are configured.</p>@endforelse
        </div>
    </section>

    <form method="POST" enctype="multipart/form-data" action="{{ route('employee.leave.store') }}"
          class="max-w-5xl"
          x-data="{
              today: @js($today),
              choices: @js($leaveChoices),
              category: @js((string) old('leave_category', '')),
              selected: @js((string) old('leave_type_id', '')),
              start: @js((string) old('start_date', '')),
              end: @js((string) old('end_date', '')),
              halfDay: @js((string) old('half_day', '')),
              get options() { return this.choices[this.category] || [] },
              get leaveType() { return this.options.find(type => type.id === this.selected) },
              get selectableOptions() { return this.options.filter(type => type.selectable) },
              get allowsHalfDay() { return this.leaveType?.halfDay && this.start && this.start === this.end },
              get days() {
                  if (!this.start || !this.end || this.end < this.start) return '—'
                  if (this.allowsHalfDay && this.halfDay) return '0.5'
                  return String((Date.parse(this.end) - Date.parse(this.start)) / 86400000 + 1)
              },
              get numericDays() { return this.days === '—' ? 0 : Number(this.days) },
              get projectedAvailable() { return this.leaveType?.balanceBased ? Number(this.leaveType.available) - this.numericDays : null },
              get insufficient() { return this.projectedAvailable !== null && this.numericDays > 0 && this.projectedAvailable < 0 },
              get cannotSubmit() { return !this.leaveType || !this.leaveType.selectable || this.insufficient },
              choose(category) {
                  this.category = category
                  this.selected = this.selectableOptions.length === 1 ? this.selectableOptions[0].id : ''
                  this.halfDay = ''
              }
          }">
        @csrf
        <input type="hidden" name="leave_type_id" :value="selected">

        <div class="rounded-md bg-white p-4 text-slate-950 sm:p-6">
            <header class="grid grid-cols-2 items-baseline gap-2 pb-3 sm:grid-cols-[1fr_auto_1fr]">
                <strong class="col-start-1 row-start-1">LMPC</strong>
                <h2 class="col-span-2 row-start-2 text-center text-base font-bold sm:col-span-1 sm:col-start-2 sm:row-start-1 sm:text-lg">LEAVE APPLICATION FORM</h2>
                <p class="col-start-2 row-start-1 text-right text-sm sm:col-start-3">HRD No. 23/Rev.2</p>
            </header>

            <div class="border-double border-4 border-slate-800 p-4 sm:p-6">
                <div class="grid gap-x-8 gap-y-4 sm:grid-cols-2">
                    <div class="min-w-0 space-y-3">
                        <dl class="space-y-3">
                            <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                                <dt class="font-semibold">Name</dt>
                                <dd class="border-b border-slate-500 break-words">{{ $employee->full_name }}</dd>
                            </div>
                            <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                                <dt class="font-semibold">Sect/Dept/Div.</dt>
                                <dd class="border-b border-slate-500 break-words">{{ $employee->department?->department_name ?? 'Unassigned' }}</dd>
                            </div>
                        </dl>
                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                            <label class="font-semibold" for="leave-day-count">No. of Days</label>
                            <output id="leave-day-count" for="start_date end_date half_day" class="block border-b border-slate-500 tabular-nums" x-text="days">—</output>
                        </div>
                    </div>
                    <div class="min-w-0 space-y-3">
                        <dl class="grid grid-cols-[7rem_minmax(0,1fr)] items-baseline gap-2">
                            <dt class="font-semibold">Date Filed</dt>
                            <dd class="border-b border-slate-500">{{ $dateFiled }}</dd>
                        </dl>
                        <fieldset class="@container min-w-0">
                            <legend class="mb-2 font-semibold">Inclusive Dates</legend>
                            <div class="grid gap-x-3 @min-[25rem]:grid-cols-2">
                                <x-input name="start_date" label="Start Date" type="date" required :min="$today" x-model="start" x-on:change="if (end && end < $event.target.value) end = ''; halfDay = ''" />
                                <x-input name="end_date" label="End Date" type="date" required :min="$today" x-model="end" x-bind:min="start > today ? start : today" x-on:change="halfDay = ''" />
                            </div>
                        </fieldset>
                        <div x-show="allowsHalfDay" x-cloak>
                            <x-select name="half_day" label="Half-Day Selection" :options="['am' => 'AM', 'pm' => 'PM']" placeholder="Full day" x-model="halfDay" x-bind:disabled="!allowsHalfDay" />
                        </div>
                        @if($errors->has('half_day'))<p class="field-error">{{ $errors->first('half_day') }}</p>@endif
                    </div>
                </div>

                <fieldset class="record-fieldset my-4 pb-4">
                    <legend>Leave type</legend>
                    @if($allAnnualCreditsUnavailable)
                        <div class="mb-4 rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950">
                            <h3 class="font-semibold">No Available Annual Leave Credits</h3>
                            <p class="mt-1">You currently have no available annual leave credits. Review your leave balance history or contact HR for assistance. Eligible special leave pathways remain available.</p>
                        </div>
                    @endif
                    <div class="flex flex-wrap gap-x-6 gap-y-3">
                        @foreach($leaveLabels as $category => $label)
                            @php
                                $categoryChoices = $leaveChoices->get($category, collect());
                                $categorySelectable = $categoryChoices->contains(fn (array $choice): bool => $choice['selectable']);
                                $singleChoice = $categoryChoices->count() === 1 ? $categoryChoices->first() : null;
                            @endphp
                            <label class="inline-flex cursor-pointer items-center gap-2 font-semibold has-disabled:cursor-default has-disabled:text-slate-500">
                                <input type="radio" name="leave_category" value="{{ $category }}" class="size-5 shrink-0 cursor-inherit appearance-none rounded-sm border border-current bg-white checked:bg-(--purple-action) checked:shadow-[inset_0_0_0_3px_white] focus-visible:outline-2 focus-visible:outline-offset-2 disabled:bg-slate-100"
                                       @checked(old('leave_category') === $category)
                                       @disabled(! $categorySelectable)
                                       x-on:change="choose(@js($category))" required>
                                <span>{{ $label }}@if($singleChoice) <span class="block text-xs font-normal">{{ $singleChoice['availabilityLabel'] }}</span>@endif</span>
                            </label>
                        @endforeach
                    </div>
                    @if($types->isNotEmpty() && $unavailableTypes)
                        <p class="mt-3 text-sm text-slate-600">Contact HR to enable {{ implode(', ', $unavailableTypes) }}.</p>
                    @endif
                    @error('leave_category')<p class="field-error">{{ $message }}</p>@enderror
                    @error('leave_type_id')<p class="field-error">{{ $message }}</p>@enderror

                    @if($allLeaveChoices->contains(fn (array $choice): bool => ! $choice['selectable']))
                        <ul class="mt-4 space-y-2" aria-label="Unavailable leave types">
                            @foreach($allLeaveChoices->where('selectable', false) as $choice)
                                <li class="rounded-md border border-slate-200 bg-slate-50 p-3 text-sm text-slate-700">
                                    <strong>{{ $choice['name'] }} — {{ $choice['availabilityLabel'] }}</strong>
                                    <span class="mt-1 block">{{ $choice['unavailableReason'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <div class="mt-4 max-w-md" x-show="options.length > 1" x-cloak>
                        <label for="leave-type-choice" class="field-label">Specify leave type</label>
                        <select id="leave-type-choice" class="field mt-2" x-model="selected" x-on:change="halfDay = ''" x-bind:required="options.length > 1" aria-describedby="leave-type-choice-help">
                            <option value="" disabled>Choose a type</option>
                            <template x-for="type in options" :key="type.id">
                                <option :value="type.id" x-bind:disabled="!type.selectable" x-text="type.name + ' — ' + type.availabilityLabel"></option>
                            </template>
                        </select>
                        <p id="leave-type-choice-help" class="small muted mt-2">Unavailable choices remain visible but cannot be selected.</p>
                    </div>
                    <p class="mt-3 text-sm" x-show="category === 'other' && leaveType && !leaveType.specify" x-text="leaveType?.name" x-cloak></p>
                    <div class="mt-4 max-w-md" x-show="category === 'other' && leaveType?.specify" x-cloak>
                        <x-input name="other_leave_type" label="Specify other leave" maxlength="150" x-bind:required="category === 'other' && !!leaveType?.specify" x-bind:disabled="category !== 'other' || !leaveType?.specify" />
                    </div>
                </fieldset>

                <section x-show="leaveType?.balanceBased" x-cloak class="my-4 rounded-lg border border-slate-200 bg-slate-50 p-4" aria-labelledby="credit-projection-heading" aria-live="polite">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div><h3 id="credit-projection-heading" class="font-semibold">Credit availability</h3><p class="small muted">Pending applications are already reserved.</p></div>
                        <div class="text-right"><strong class="text-xl tabular-nums" x-text="$leaveDays(leaveType?.available || 0)"></strong><p class="small muted">days available</p></div>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-3">
                        <div><dt class="muted">Total credits</dt><dd class="font-semibold tabular-nums" x-text="$leaveDaysWithUnit(leaveType?.entitled || 0)"></dd></div>
                        <div><dt class="muted">Approved / committed</dt><dd class="font-semibold tabular-nums" x-text="$leaveDaysWithUnit(leaveType?.approved || 0)"></dd></div>
                        <div><dt class="muted">Pending</dt><dd class="font-semibold tabular-nums" x-text="$leaveDaysWithUnit(leaveType?.pending || 0)"></dd></div>
                        <div><dt class="muted">This request</dt><dd class="font-semibold tabular-nums" x-text="numericDays ? $leaveDaysWithUnit(numericDays) : 'Select dates'"></dd></div>
                        <div><dt class="muted">Projected after approval</dt><dd class="font-semibold tabular-nums" x-text="projectedAvailable === null ? '—' : $leaveDaysWithUnit(projectedAvailable)"></dd></div>
                    </dl>
                    <p x-show="insufficient" x-cloak class="mt-3 text-sm font-semibold text-red-700" x-text="'Insufficient ' + leaveType.name + ' credits. You have ' + $leaveDaysWithUnit(leaveType.available) + ' available, but your application requires ' + $leaveDaysWithUnit(numericDays) + '.'"></p>
                    @error('requested_duration')<p class="field-error mt-3">{{ $message }}</p>@enderror
                    <p class="small muted mt-3">The projection uses calendar dates. Your supervisor confirms the final charge under the saved schedule and holiday policy.</p>
                </section>

                <label class="field-label font-semibold" for="leave-reason">Reason</label>
                <textarea class="field attendance-textarea mt-2" id="leave-reason" name="reason" required minlength="10" maxlength="2000">{{ old('reason') }}</textarea>
                @error('reason')<p class="field-error">{{ $message }}</p>@enderror

                <div class="mt-4 max-w-md" x-show="leaveType?.documentRequired || @js($errors->has('supporting_document'))" x-cloak>
                    <x-input name="supporting_document" label="Supporting Document" type="file" accept=".pdf,.jpg,.jpeg,.png" aria-describedby="leave-document-formats" x-bind:required="!!leaveType?.documentRequired" x-bind:disabled="!leaveType?.documentRequired" />
                    <p id="leave-document-formats" class="small muted">Accepted formats: PDF, JPEG, and PNG.</p>
                    <p class="small muted">PDF, JPEG or PNG, up to {{ number_format(config('leave.document_max_kilobytes') / 1024, 1) }} MB.</p>
                </div>
            </div>
            <p class="mt-3 text-sm text-slate-600">The day count includes both dates. Your Supervisor confirms the chargeable days under your schedule and leave policy.</p>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-button type="submit" :disabled="$types->isEmpty()" x-bind:disabled="cannotSubmit">Submit Application</x-button>
            <x-button variant="secondary" :href="route('employee.leave.index')">Cancel</x-button>
        </div>
    </form>
</x-layouts.app>
