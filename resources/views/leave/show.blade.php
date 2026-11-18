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
        $official = $leave->approvalDocument;
        $timezone = config('attendance.timezone');
    @endphp
    <x-page-header :title="'Leave application #'.$leave->id" :description="$name.' · '.$typeName">
        <x-button variant="secondary" :href="route($prefix.'.index')">Back to leave</x-button>
    </x-page-header>
    <div class="content-grid">
        <div class="stack">
            <section class="card">
                <div class="card-heading"><h2>Application details</h2><x-state :value="$leave->status" /></div>
                <div class="card-body">
                    <div class="profile-row">
                        <span class="muted">Employee</span>
                        @if($scope === 'supervisor' && auth()->user()->can('view', $leave->employee))
                            <a class="text-link" href="{{ route('supervisor.leave.employees.profile', [$leave->employee, 'leave' => $leave->id]) }}">{{ $name }}</a>
                        @elseif($scope === 'hr')
                            <a class="text-link" href="{{ route('hr.employees.show', $leave->employee) }}">{{ $name }}</a>
                        @else
                            <span>{{ $name }}</span>
                        @endif
                    </div>
                    @foreach(['Department' => $department, 'Branch' => $branch, 'Leave type' => $typeName, 'Inclusive dates' => $dates, 'No. of days' => $days, 'Supervisor' => $leave->supervisor?->name ?? 'Awaiting assignment', 'Date filed' => $leave->submitted_at->timezone($timezone)->format('M j, Y H:i')] as $label => $value)
                        <div class="profile-row"><span class="muted">{{ $label }}</span><span>{{ $value ?? 'Unassigned' }}</span></div>
                    @endforeach
                    <h3 class="mt-5 mb-3">Reason</h3>
                    <p class="leading-6 whitespace-pre-wrap break-words">{{ $leave->reason }}</p>
                    @if($leave->charged_duration !== null && $leave->type_snapshot['affects_balance'])
                        <p class="small mt-5">Final confirmed charge: {{ $leave->charged_duration }} days.</p>
                    @endif
                </div>
            </section>

            <section class="card">
                <div class="card-heading"><h2>LIMCOMA Leave Application Form</h2></div>
                <div class="card-body">
                    @if($official)
                        <div class="profile-row"><span class="muted">Approved by</span><span>{{ $official->approver_name }}</span></div>
                        <div class="profile-row"><span class="muted">Approval date</span><span>{{ $leave->finalized_at?->timezone($timezone)->format('M j, Y H:i:s T') }}</span></div>
                        <p class="small muted mt-4">{{ $official->template_reference }} · {{ $official->document_reference }}</p>
                        @if($official->revoked_at)<p class="mt-3 text-sm font-semibold text-amber-900">Document revoked</p>@endif
                        <div class="mt-4 flex flex-wrap gap-3">
                            <x-button variant="secondary" :href="route('leave.verify.show', $official)">Verify Document</x-button>
                            @if(!$official->revoked_at)
                            <x-button variant="secondary" :href="route($prefix.'.approved-form', $leave)" target="_blank" rel="noopener">View Approved Form</x-button>
                            <x-button variant="secondary" :href="route($prefix.'.approved-form', [$leave, 'download' => 1])">Download Approved PDF</x-button>
                            @endif
                        </div>
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
                        <a class="text-link" href="{{ route($prefix.'.form', $leave) }}" target="_blank" rel="noopener">View Application <span class="small">(opens in a new tab)</span></a>
                        @if($scope === 'supervisor' && $pending)
                            <iframe class="mt-4 h-[34rem] w-full rounded border border-slate-200" src="{{ route($prefix.'.form', $leave) }}" title="Printable leave application preview" loading="lazy"></iframe>
                        @endif
                    @endif
                </div>
            </section>

            <section class="card">
                <div class="card-heading"><h2>Supporting documents</h2></div>
                <div class="card-body">
                    @forelse($leave->documents as $document)
                        <div class="profile-row"><span>{{ $document->original_name }}</span>
                            @if($document->purged_at)<span>Removed under configured retention</span>@else<a class="text-link" href="{{ route($prefix.'.document', $document) }}">Download document</a>@endif
                        </div>
                    @empty
                        <p class="small muted">No documents attached.</p>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="card-heading"><h2>Review history</h2></div>
                <div class="card-body">
                    @foreach($leave->reviews as $review)
                        <article class="profile-row">
                            <div><strong>{{ ucwords(str_replace('_', ' ', $review->action)) }}</strong><p class="small muted">{{ $review->reviewed_at->timezone($timezone)->format('M j, Y H:i') }} · {{ $review->reviewer?->name ?? 'Scheduled routing' }}</p></div>
                            <div><p class="small leading-6 whitespace-pre-wrap">{{ $review->remarks }}</p>
                                @if(array_key_exists('supervisor_id', $review->metadata ?? []))<p class="small muted">Reviewer: {{ $review->metadata['previous_supervisor_id'] ?? 'None' }} → {{ $review->metadata['supervisor_id'] ?? 'Awaiting assignment' }}</p>@endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="stack">
            @if($leave->type_snapshot['affects_balance'])
                <section class="card"><div class="card-heading"><h2>Applicable balance</h2></div><div class="card-body"><p>{{ $balance ? $balance->available_days.' days available · '.$balance->policy_period : 'No balance configured for this type/year.' }}</p></div></section>
            @endif
            @if($scope === 'supervisor' && $leave->status === 'pending_supervisor_approval')
                <section class="card">
                    <div class="card-heading"><h2>Final Supervisor decision</h2></div>
                    <div class="card-body">
                        @error('decision')<p class="field-error mb-3">{{ $message }}</p>@enderror
                        @error('signature_version')<p class="field-error mb-3">{{ $message }}</p>@enderror
                        @error('confirmation')<p class="field-error mb-3">{{ $message }}</p>@enderror
                        @if($signatureVersion)
                            <p class="small mb-4">Electronic signature registered · Version {{ $signatureVersion }}</p>
                            <form method="POST" action="{{ route('supervisor.leave.decide', $leave) }}"
                                  data-confirm-title="Approve and Sign Leave?"
                                  data-confirm-text="Your registered electronic signature will be applied to the official LIMCOMA Leave Application Form. This will finalize the leave decision. {{ $name }} · {{ $typeName }} · {{ $dates }} · {{ $days }} days"
                                  data-confirm-button="Approve &amp; Sign">
                                @csrf
                                <input type="hidden" name="decision" value="approved">
                                <input type="hidden" name="signature_version" value="{{ $signatureVersion }}">
                                <input type="hidden" name="confirmation" value="0" data-confirm-on-accept>
                                @if($leave->type->affects_balance)
                                    <x-input name="chargeable_days" label="Confirmed chargeable days" type="number" step="0.5" min="0" :max="$leave->requested_duration" required />
                                    <p class="small muted mb-4">Confirm days using the actual work schedule and holidays.</p>
                                @endif
                                <x-password name="current_password" label="Confirm your current password" />
                                <x-button type="submit">Approve &amp; Sign</x-button>
                            </form>
                        @else
                            <p class="small mb-3">Register your electronic signature to approve an application.</p>
                            <x-button variant="secondary" :href="route('supervisor.signature.show')">Register signature</x-button>
                        @endif
                        <form method="POST" class="mt-5 border-t border-slate-200 pt-5" action="{{ route('supervisor.leave.decide', $leave) }}"
                              data-confirm-title="Reject Leave Application?" data-confirm-text="Reject this leave application with the remarks entered?" data-confirm-button="Reject Leave" data-confirm-danger="true">
                            @csrf
                            <input type="hidden" name="decision" value="rejected">
                            <input type="hidden" name="confirmation" value="1">
                            <div class="field-label-row"><label class="field-label" for="leave-remarks">Rejection reason</label><span class="field-required" aria-hidden="true">Required</span></div>
                            <textarea id="leave-remarks" name="remarks" class="field attendance-textarea mb-3" required minlength="10" maxlength="2000">{{ old('remarks') }}</textarea>
                            @error('remarks')<p class="field-error mb-3">{{ $message }}</p>@enderror
                            <x-button type="submit" variant="danger">Reject Leave</x-button>
                        </form>
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
            @if($leave->employee->user_id === auth()->id() && $pending)
                <form method="POST" class="card" action="{{ route('employee.leave.cancel', $leave) }}" data-confirm-title="Cancel pending leave?" data-confirm-button="Cancel leave" data-confirm-danger="true">
                    @csrf<input type="hidden" name="confirmation" value="1">
                    <div class="card-heading"><h2>Cancel pending application</h2></div>
                    <div class="card-body">
                        <x-input name="reason" label="Cancellation reason" required minlength="10" maxlength="1000" />
                        <x-button type="submit" variant="danger">Cancel application</x-button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
