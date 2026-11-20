<x-layouts.app title="Leave Application">
    @php
        $prefix = $scope.'.leave';
        $fields = $leave->form_snapshot;
        $name = $fields['name'] ?? $leave->employee->full_name;
        $department = $fields ? $fields['department'] : $leave->employee->department?->department_name;
        $branch = $fields ? $fields['branch'] : $leave->employee->currentAssignment?->branch?->branch_name;
        $typeName = $leave->type_snapshot['name'];
        $dates = $leave->start_date->format('M j, Y').' – '.$leave->end_date->format('M j, Y');
        $days = $leave->requested_duration.($leave->half_day ? ' · '.strtoupper($leave->half_day) : '');
        $pending = in_array($leave->status, \App\Models\LeaveRequest::PENDING, true);
        $stackPendingCards = $pending && $scope !== 'supervisor';
        $official = $leave->approvalDocument;
        $timezone = config('attendance.timezone');
    @endphp
    <x-page-header :title="'Leave application #'.$leave->id" :description="$name.' · '.$typeName">
        @if($scope === 'supervisor' && $leave->status === 'pending_supervisor_approval')
            <x-button href="#leave-decision">Review decision</x-button>
        @endif
        <x-button variant="secondary" :href="route($prefix.'.index')">Back to leave</x-button>
    </x-page-header>
    @php($originalOverlap = data_get($leave->form_snapshot, 'attendance_policy.holiday_dates', []))
    @if($holidayOverlap || $originalOverlap)
        <section class="card mb-6">
            <div class="card-heading"><h2>Applicable holiday overlap</h2></div>
            <div class="card-body space-y-2">
                <p>Current calendar: {{ implode(', ', $holidayOverlap) ?: 'None' }}</p>
                @if($holidayOverlap !== $originalOverlap)<p class="small muted">Review needed. At submission: {{ implode(', ', $originalOverlap) ?: 'None recorded' }}.</p>@endif
                @if($excludedDates = data_get($leave->form_snapshot, 'attendance_policy.excluded_dates'))
                    <p class="small muted">Dates excluded from the saved maximum charge: {{ implode(', ', $excludedDates) }}.</p>
                @endif
            </div>
        </section>
    @endif
    <div class="@container/leave-details">
        <div @class([
            'grid w-full grid-cols-1 items-start gap-4',
            '@min-[56rem]/leave-details:grid-cols-[minmax(0,9fr)_minmax(0,11fr)]' => $scope === 'supervisor',
            'lg:@min-[40rem]/leave-details:grid-cols-2' => $scope !== 'supervisor',
        ])>
            <div @class(['contents', '@min-[56rem]/leave-details:grid @min-[56rem]/leave-details:gap-4 min-w-0' => $scope === 'supervisor', 'lg:@min-[40rem]/leave-details:grid lg:@min-[40rem]/leave-details:gap-4 min-w-0' => $stackPendingCards])>
                <section class="card order-1 min-w-0">
                    <div class="card-heading px-5! py-3.5!"><h2>Application details</h2><span class="inline-flex flex-wrap items-center gap-1.5"><x-state :value="$leave->status" /><x-leave-period :leave="$leave" /></span></div>
                    <div class="px-5 py-4">
                        <dl class="divide-y divide-slate-100">
                            <div class="grid grid-cols-[minmax(6rem,2fr)_minmax(0,3fr)] gap-3 py-2 text-sm wrap-anywhere">
                                <dt class="muted">Employee</dt>
                                <dd>
                                    @if($scope === 'supervisor' && auth()->user()->can('view', $leave->employee))
                                        <a class="text-link" href="{{ route('supervisor.leave.employees.profile', [$leave->employee, 'leave' => $leave->id]) }}">{{ $name }}</a>
                                    @elseif($scope === 'hr')
                                        <a class="text-link" href="{{ route('hr.employees.show', $leave->employee) }}">{{ $name }}</a>
                                    @else
                                        <span>{{ $name }}</span>
                                    @endif
                                </dd>
                            </div>
                            @foreach(['Department' => $department, 'Branch' => $branch, 'Leave type' => $typeName, 'Inclusive dates' => $dates, 'No. of days' => $days, 'Supervisor' => $leave->supervisor?->name ?? 'Awaiting assignment', 'Date filed' => $leave->submitted_at->timezone($timezone)->format('M j, Y H:i')] as $label => $value)
                                <div class="grid grid-cols-[minmax(6rem,2fr)_minmax(0,3fr)] gap-3 py-2 text-sm wrap-anywhere"><dt class="muted">{{ $label }}</dt><dd>{{ $value ?? 'Unassigned' }}</dd></div>
                            @endforeach
                        <div class="grid grid-cols-[minmax(6rem,2fr)_minmax(0,3fr)] gap-3 py-2 text-sm wrap-anywhere">
                                <dt class="muted">Reason</dt>
                                <dd class="whitespace-pre-wrap">{{ $leave->reason }}</dd>
                            </div>
                        </dl>
                        @if($leave->charged_duration !== null && $leave->type_snapshot['affects_balance'])
                            <p class="small mt-3">Final confirmed charge: {{ $leave->charged_duration }} days.</p>
                        @endif
                    </div>
                </section>

                <section class="card {{ $stackPendingCards ? 'order-2' : 'order-3' }} min-w-0" x-data="leaveDocumentPreview">
                    <div class="card-heading px-5! py-3.5!"><h2>Supporting documents</h2></div>
                    <div class="px-5 py-4">
                        @if($leave->documents->isEmpty())
                            <p class="small muted">No documents attached.</p>
                        @else
                            <ul class="divide-y divide-slate-100">
                                @foreach($leave->documents as $document)
                                    <li class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 py-2 text-sm">
                                        <span class="flex min-w-0 items-center gap-2 wrap-anywhere"><x-icon name="file" class="size-4 shrink-0 text-slate-500" /><span class="min-w-0" id="leave-document-{{ $document->id }}">{{ $document->original_name }}</span></span>
                                        @if($document->purged_at)
                                            <span class="small muted">Removed under configured retention</span>
                                        @else
                                            <div class="ml-auto flex flex-wrap items-center justify-end gap-x-4 gap-y-2">
                                                @if($scope !== 'supervisor' || auth()->user()->can('review', $leave))
                                                    <button type="button" class="text-link cursor-pointer" aria-haspopup="dialog" aria-describedby="leave-document-{{ $document->id }}"
                                                        data-name="{{ $document->original_name }}" data-preview="{{ route($prefix.'.document', [$document, 'preview' => 1]) }}"
                                                        data-download="{{ route($prefix.'.document', $document) }}" @click="view($el)">View document</button>
                                                @endif
                                                <a class="text-link" aria-describedby="leave-document-{{ $document->id }}" href="{{ route($prefix.'.document', $document) }}">Download document</a>
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                            @include('leave.document-preview')
                        @endif
                    </div>
                </section>

                @if($scope === 'supervisor')
                    @include('leave.review-history')
                @endif
            </div>

            <div @class(['contents', 'lg:@min-[40rem]/leave-details:grid lg:@min-[40rem]/leave-details:gap-4 min-w-0' => $stackPendingCards])>
                <section class="card {{ $stackPendingCards ? 'order-3' : 'order-2' }} min-w-0">
                    <div class="card-heading px-5! py-3.5!"><h2>LIMCOMA Leave Application Form</h2></div>
                    <div class="px-5 py-4">
                        @if($official)
                            <dl class="divide-y divide-slate-100">
                                <div class="grid grid-cols-[minmax(6rem,2fr)_minmax(0,3fr)] gap-3 py-2 text-sm wrap-anywhere"><dt class="muted">Approved by</dt><dd>{{ $official->approver_name }}</dd></div>
                                <div class="grid grid-cols-[minmax(6rem,2fr)_minmax(0,3fr)] gap-3 py-2 text-sm wrap-anywhere"><dt class="muted">Approval date</dt><dd>{{ $leave->finalized_at?->timezone($timezone)->format('M j, Y H:i:s T') }}</dd></div>
                            </dl>
                            <p class="small muted mt-2 wrap-anywhere">{{ $official->template_reference }} · {{ $official->document_reference }}</p>
                            @if($official->revoked_at)<p class="mt-3 text-sm font-semibold text-amber-900">Document revoked</p>@endif
                            @if(!$official->revoked_at)
                                <div class="mt-3 flex flex-wrap gap-2">
                                    <x-button variant="secondary" :href="route($prefix.'.approved-form', $leave)" target="_blank" rel="noopener">View Approved Form</x-button>
                                    <x-button variant="secondary" :href="route($prefix.'.approved-form', [$leave, 'download' => 1])">Download Approved PDF</x-button>
                                </div>
                            @endif
                            @if(($scope === 'hr' && !$official->revoked_at) || ($scope === 'supervisor' && $signatureVersion && $leave->form_snapshot))
                                <details class="mt-4">
                                    <summary class="text-link cursor-pointer">{{ $scope === 'hr' ? 'Revoke approved leave' : 'Sign a replacement PDF' }}</summary>
                                    <form method="POST" class="mt-3" action="{{ route($scope === 'hr' ? 'leave.verify.revoke' : 'leave.verify.replace', $official) }}"
                                          data-confirm-title="{{ $scope === 'hr' ? 'Revoke this approved leave?' : 'Sign and issue a replacement?' }}"
                                          data-confirm-text="{{ $scope === 'hr' ? 'This changes the leave request and the issued document to Revoked.' : 'Your signature will be applied to a new version using the original approved application.' }}">
                                        @csrf
                                        <input type="hidden" name="confirmation" value="0" data-confirm-on-accept>
                                        @if($scope === 'supervisor')<input type="hidden" name="signature_version" value="{{ $signatureVersion }}">@endif
                                        <x-input name="reason" label="Reason for this action" minlength="10" maxlength="500" required />
                                        <x-password name="current_password" label="Confirm your current password" />
                                        <x-button type="submit">{{ $scope === 'hr' ? 'Revoke Leave' : 'Sign Replacement' }}</x-button>
                                    </form>
                                </details>
                            @endif
                        @else
                            @if($leave->status === 'approved')<p class="small muted mb-3">This historical approval predates the signed PDF workflow.</p>@endif
                            <x-button variant="secondary" :href="route($prefix.'.form', $leave)" target="_blank" rel="noopener" class="max-w-full text-left">
                                <span>View Application <span class="sr-only">(opens in a new tab)</span></span>
                                <x-icon name="external" class="size-4 shrink-0" />
                            </x-button>
                            @if($scope === 'supervisor' && $pending)
                                <iframe class="mt-4 h-[34rem] w-full rounded border border-slate-200" src="{{ route($prefix.'.form', $leave) }}" title="Printable leave application preview" loading="lazy"></iframe>
                            @endif
                        @endif
                    </div>
                </section>

                @unless($scope === 'supervisor')
                    @include('leave.review-history')
                @endunless

                @if($leave->employee->user_id === auth()->id() && $pending)
                    <form method="POST" class="card order-5 min-w-0" action="{{ route('employee.leave.cancel', $leave) }}" data-confirm-title="Cancel pending leave?" data-confirm-button="Cancel leave" data-confirm-danger="true">
                        @csrf<input type="hidden" name="confirmation" value="1">
                        <div class="card-heading px-5! py-3.5!"><h2>Cancel pending application</h2></div>
                        <div class="px-5 py-4 [&_.field-label-row]:mb-2 [&_.field-required]:font-medium">
                            <x-input name="reason" label="Cancellation reason" required minlength="10" maxlength="1000" />
                            <x-button type="submit" variant="danger" class="transition-colors">Cancel application</x-button>
                        </div>
                    </form>
                @endif
            </div>
        </div>

        <div class="grid items-start gap-4 has-[>*]:mt-4 lg:@min-[40rem]/leave-details:grid-cols-2">
            @if($leave->type_snapshot['affects_balance'])
                <section class="card"><div class="card-heading"><h2>Applicable balance</h2></div><div class="card-body"><p>{{ $balance ? $balance->available_days.' days available · '.$balance->policy_period : 'No balance configured for this type/year.' }}</p></div></section>
            @endif
            @if($scope === 'supervisor' && $leave->status === 'pending_supervisor_approval')
                <section class="card col-span-full min-w-0" key="leave-decision-{{ $leave->id }}">
                    <div class="card-heading px-5! py-3.5!"><h2 id="leave-decision" tabindex="-1">Final Supervisor decision</h2></div>
                    <div class="px-5 py-4">
                        <div key="leave-decision-errors">
                            @error('decision')<p class="field-error mb-3">{{ $message }}</p>@enderror
                            @error('signature_version')<p class="field-error mb-3">{{ $message }}</p>@enderror
                            @error('confirmation')<p class="field-error mb-3">{{ $message }}</p>@enderror
                        </div>
                        <div class="grid gap-4 @min-[48rem]/leave-details:grid-cols-2">
                            <div class="min-w-0">
                                @if($signatureVersion)
                                    <p class="small mb-4">Electronic signature registered · Version {{ $signatureVersion }}</p>
                                    <form method="POST" action="{{ route('supervisor.leave.decide', $leave) }}" key="approve-leave-{{ $leave->id }}">
                                        @csrf
                                        <input type="hidden" name="decision" value="approved">
                                        <input type="hidden" name="signature_version" value="{{ $signatureVersion }}">
                                        @if($leave->type->affects_balance)
                                            <x-input name="chargeable_days" label="Confirmed chargeable days" type="number" step="0.5" min="0" :max="data_get($leave->form_snapshot, 'attendance_policy.maximum_charge', $leave->requested_duration)" required />
                                            <p class="small muted mb-4">Confirm days using the actual work schedule and holidays.</p>
                                        @endif
                                        <x-password name="current_password" label="Confirm your current password" />
                                        <p class="small muted mb-3">Approve &amp; Sign applies your registered electronic signature and finalizes this decision.</p>
                                        <x-button type="submit" name="confirmation" value="1">Approve &amp; Sign</x-button>
                                    </form>
                                @else
                                    <p class="small mb-3">Register your electronic signature to approve an application.</p>
                                    <x-button variant="secondary" :href="route('supervisor.signature.show')">Register signature</x-button>
                                @endif
                            </div>
                            <form method="POST" class="min-w-0 border-t border-slate-200 pt-4 @min-[48rem]/leave-details:border-t-0 @min-[48rem]/leave-details:border-l @min-[48rem]/leave-details:pt-0 @min-[48rem]/leave-details:pl-4" action="{{ route('supervisor.leave.decide', $leave) }}" key="reject-leave-{{ $leave->id }}"
                                  data-confirm-title="Reject Leave Application?" data-confirm-text="Reject this leave application with the remarks entered?" data-confirm-button="Reject Leave" data-confirm-danger="true">
                                @csrf
                                <input type="hidden" name="decision" value="rejected">
                                <input type="hidden" name="confirmation" value="1">
                                <div class="field-label-row"><label class="field-label" for="leave-remarks">Rejection reason</label><span class="field-required" aria-hidden="true">Required</span></div>
                                <textarea id="leave-remarks" name="remarks" @error('remarks') aria-invalid="true" aria-describedby="leave-remarks-error" @enderror class="field attendance-textarea mb-3" required minlength="10" maxlength="2000">{{ old('remarks') }}</textarea>
                                @error('remarks')<p id="leave-remarks-error" class="field-error mb-3">{{ $message }}</p>@enderror
                                <x-button type="submit" variant="danger">Reject Leave</x-button>
                            </form>
                        </div>
                    </div>
                </section>
            @endif
            @if($scope === 'hr' && $pending)
                <section class="card">
                    <div class="card-heading"><h2>Assignment administration</h2></div>
                    <div class="card-body">
                        <x-button variant="secondary" :href="route('hr.supervisors.index')">Manage assignments</x-button>
                        <form method="POST" class="mt-4" action="{{ route('hr.leave.route', $leave) }}" data-confirm-title="Refresh leave routing?" data-confirm-button="Refresh routing">
                            @csrf<input type="hidden" name="confirmation" value="1"><x-button type="submit" variant="secondary">Refresh routing</x-button>
                        </form>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
