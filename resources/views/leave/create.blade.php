<x-layouts.app title="Apply for Leave">
    <x-page-header title="Apply for Leave" description="Complete the LMPC leave application." />

    @php
        $leaveLabels = ['vacation' => 'Vacation Leave', 'sick' => 'Sick Leave', 'other' => 'Others (pls specify)'];
        $unavailableTypes = array_diff_key($leaveLabels, $leaveChoices->all());
    @endphp

    @if($types->isEmpty())
        <x-alert>No leave types are configured. Contact HR.</x-alert>
    @endif

    <form method="POST" enctype="multipart/form-data" action="{{ route('employee.leave.store') }}"
          class="max-w-5xl"
          x-data="{
              choices: @js($leaveChoices),
              category: @js((string) old('leave_category', '')),
              selected: @js((string) old('leave_type_id', '')),
              start: @js((string) old('start_date', '')),
              end: @js((string) old('end_date', '')),
              halfDay: @js((string) old('half_day', '')),
              get options() { return this.choices[this.category] || [] },
              get leaveType() { return this.options.find(type => type.id === this.selected) },
              get allowsHalfDay() { return this.leaveType?.halfDay && this.start && this.start === this.end },
              get days() {
                  if (!this.start || !this.end || this.end < this.start) return '—'
                  if (this.allowsHalfDay && this.halfDay) return '0.5'
                  return String((Date.parse(this.end) - Date.parse(this.start)) / 86400000 + 1)
              },
              choose(category) {
                  this.category = category
                  this.selected = this.options.length === 1 ? this.options[0].id : ''
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
                                <x-input name="start_date" label="Start Date" type="date" required x-model="start" x-on:change="halfDay = ''" />
                                <x-input name="end_date" label="End Date" type="date" required x-model="end" x-bind:min="start || null" x-on:change="halfDay = ''" />
                            </div>
                        </fieldset>
                        <div x-show="allowsHalfDay" x-cloak>
                            <x-select name="half_day" label="Half-Day Selection" :options="['am' => 'AM', 'pm' => 'PM']" placeholder="Full day" x-model="halfDay" x-bind:disabled="!allowsHalfDay" />
                        </div>
                        @if($errors->has('half_day'))<p class="field-error">{{ $errors->first('half_day') }}</p>@endif
                    </div>
                </div>

                <fieldset class="my-4 border-t border-slate-300 pt-4">
                    <legend class="sr-only">Leave type</legend>
                    <div class="flex flex-wrap gap-x-6 gap-y-3">
                        @foreach($leaveLabels as $category => $label)
                            <label class="inline-flex cursor-pointer items-center gap-2 font-semibold has-disabled:cursor-default has-disabled:text-slate-500">
                                <input type="radio" name="leave_category" value="{{ $category }}" class="size-5 shrink-0 cursor-inherit appearance-none rounded-sm border border-current bg-white checked:bg-slate-950 checked:shadow-[inset_0_0_0_3px_white] focus-visible:outline-2 focus-visible:outline-offset-2 disabled:bg-slate-100"
                                       @checked(old('leave_category') === $category)
                                       @disabled($leaveChoices->get($category, collect())->isEmpty())
                                       x-on:change="choose(@js($category))" required>
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @if($types->isNotEmpty() && $unavailableTypes)
                        <p class="mt-3 text-sm text-slate-600">Contact HR to enable {{ implode(', ', $unavailableTypes) }}.</p>
                    @endif
                    @error('leave_category')<p class="field-error">{{ $message }}</p>@enderror
                    @error('leave_type_id')<p class="field-error">{{ $message }}</p>@enderror

                    <div class="mt-4 max-w-md" x-show="options.length > 1" x-cloak>
                        <label for="leave-type-choice" class="field-label">Specify leave type</label>
                        <select id="leave-type-choice" class="field mt-2" x-model="selected" x-on:change="halfDay = ''" x-bind:required="options.length > 1">
                            <option value="">Choose a type</option>
                            <template x-for="type in options" :key="type.id">
                                <option :value="type.id" x-text="type.name"></option>
                            </template>
                        </select>
                    </div>
                    <p class="mt-3 text-sm" x-show="category === 'other' && leaveType && !leaveType.specify" x-text="leaveType?.name" x-cloak></p>
                    <div class="mt-4 max-w-md" x-show="category === 'other' && leaveType?.specify" x-cloak>
                        <x-input name="other_leave_type" label="Specify other leave" maxlength="150" x-bind:required="category === 'other' && !!leaveType?.specify" x-bind:disabled="category !== 'other' || !leaveType?.specify" />
                    </div>
                </fieldset>

                <label class="field-label font-semibold" for="leave-reason">Reason</label>
                <textarea class="field attendance-textarea mt-2" id="leave-reason" name="reason" required minlength="10" maxlength="2000">{{ old('reason') }}</textarea>
                @error('reason')<p class="field-error">{{ $message }}</p>@enderror

                <div class="mt-4 max-w-md" x-show="leaveType?.documentRequired || @js($errors->has('supporting_document'))" x-cloak>
                    <x-input name="supporting_document" label="Supporting Document" type="file" accept=".pdf,.jpg,.jpeg,.png" x-bind:required="!!leaveType?.documentRequired" x-bind:disabled="!leaveType?.documentRequired" />
                    <p class="small muted">PDF, JPEG or PNG, up to {{ number_format(config('leave.document_max_kilobytes') / 1024, 1) }} MB.</p>
                </div>
            </div>
            <p class="mt-3 text-sm text-slate-600">The day count includes both dates. Your Supervisor confirms the chargeable days under your schedule and leave policy.</p>
        </div>

        <div class="mt-4 flex flex-wrap gap-3">
            <x-button type="submit" :disabled="$types->isEmpty()">Submit Application</x-button>
            <x-button variant="secondary" :href="route('employee.leave.index')">Cancel</x-button>
        </div>
    </form>
</x-layouts.app>
