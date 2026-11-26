<x-layouts.app title="Leave Credit Management">
    <x-page-header title="Leave Credit Management" description="Manage effective policies, verified entitlements, reservations, adjustments, and the append-only credit ledger.">
        <x-button variant="secondary" :href="route('hr.leave.balances.export', request()->query())">Export CSV</x-button>
        <x-button variant="secondary" :href="route('hr.leave.index')">Leave monitoring</x-button>
    </x-page-header>

    <section class="card mb-6" aria-labelledby="credit-report-heading">
        <div class="card-heading"><h2 id="credit-report-heading">Credit report</h2><span class="small muted">{{ $year }}</span></div>
        <form method="GET" action="{{ route('hr.leave.balances') }}" class="toolbar" aria-label="Leave credit report filters">
            <x-select name="employee_id" label="Employee" :options="$employees->mapWithKeys(fn ($employee) => [$employee->id => $employee->full_name.' · '.$employee->employee_number])" :value="request('employee_id')" placeholder="All employees" />
            <x-select name="leave_type_id" label="Leave type" :options="$types->pluck('name', 'id')" :value="request('leave_type_id')" placeholder="All types" />
            <x-input name="year" label="Policy year" :value="$year" pattern="20[0-9]{2}" inputmode="numeric" required />
            <x-button type="submit">Apply filters</x-button>
        </form>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Leave credit balances">
            <x-table :columns="['Employee', 'Leave type', 'Entitled', 'Approved', 'Pending', 'Remaining', 'Available', 'Ledger']" caption="Leave credit balances">
                @forelse($balances as $balance)
                    @php($summary = $balance->credit_summary)
                    <tr>
                        <td>{{ $balance->employee->full_name }}<br><span class="small muted">{{ $balance->employee->employee_number }}</span></td>
                        <td>{{ $balance->type->name }}<br><span class="small muted">{{ $balance->policy_period }}</span>@if($summary['event_reference'])<br><span class="small">{{ $summary['eligibility_status'] }} · {{ $summary['event_reference'] }}</span>@endif</td>
                        <td>{{ \App\Support\LeaveDays::format($summary['entitled']) }}</td><td>{{ \App\Support\LeaveDays::format($summary['approved']) }}</td><td>{{ \App\Support\LeaveDays::format($summary['pending']) }}</td><td>{{ \App\Support\LeaveDays::format($summary['remaining']) }}</td><td><strong>{{ \App\Support\LeaveDays::format($summary['available']) }}</strong></td>
                        <td><details><summary class="text-link cursor-pointer">{{ $balance->transactions->count() }} entries</summary><div class="mt-3 min-w-80 space-y-3">@forelse($balance->transactions as $transaction)<article class="rounded border border-slate-200 p-3 text-sm"><div class="flex flex-wrap justify-between gap-2"><strong>{{ str_replace('_', ' ', $transaction->transaction_type) }}</strong><span class="font-semibold {{ (float) $transaction->amount >= 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ (float) $transaction->amount >= 0 ? '+' : '' }}{{ \App\Support\LeaveDays::format($transaction->amount) }}</span></div><p class="small muted">{{ ($transaction->effective_on ?? $transaction->created_at)->format('M j, Y') }} · {{ $transaction->authorizer->name }}</p><p class="mt-1">{{ $transaction->reason }}</p></article>@empty<p class="small muted">No ledger entries.</p>@endforelse</div></details></td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty-state title="No balances found" description="Allocate annual credits, grant a verified event entitlement, or change the filters." icon="calendar" /></td></tr>
                @endforelse
            </x-table>
        </div>
        <div class="card-body">{{ $balances->links() }}</div>
    </section>

    <div class="grid items-start gap-6 xl:grid-cols-3">
        <form method="POST" class="card" action="{{ route('hr.leave.balances.adjust') }}" aria-labelledby="adjustment-heading" data-confirm-title="Record leave credit adjustment?" data-confirm-text="This records an append-only ledger entry and updates the balance projection." data-confirm-button="Record adjustment">
            @csrf<input type="hidden" name="confirmation" value="1"><div class="card-heading"><h2 id="adjustment-heading">Authorized adjustment</h2></div>
            <div class="card-body"><x-select name="employee_id" label="Employee" :options="$employees->mapWithKeys(fn ($employee) => [$employee->id => $employee->full_name.' · '.$employee->employee_number])" required /><x-select name="leave_type_id" label="Leave type" :options="$types->pluck('name', 'id')" required /><x-input name="policy_period" label="Policy year" :value="$year" pattern="20[0-9]{2}" inputmode="numeric" required /><x-input name="amount" label="Days (+ credit / − deduction)" type="number" step="0.5" min="-9999" max="9999" required /><x-input name="reason" label="Authorization and reason" required minlength="10" maxlength="1000" /><x-button type="submit">Record adjustment</x-button></div>
        </form>

        <form method="POST" class="card" action="{{ route('hr.leave.event-entitlements.store') }}" aria-labelledby="event-grant-heading" data-confirm-title="Grant verified event entitlement?" data-confirm-text="Confirm that HR reviewed the employee's eligibility evidence for this event." data-confirm-button="Grant entitlement">
            @csrf<input type="hidden" name="confirmation" value="1"><div class="card-heading"><h2 id="event-grant-heading">Verified event entitlement</h2></div>
            <div class="card-body"><x-select name="employee_id" label="Employee" :options="$employees->mapWithKeys(fn ($employee) => [$employee->id => $employee->full_name.' · '.$employee->employee_number])" required /><x-select name="leave_policy_id" label="Effective event policy" :options="$eventPolicies->mapWithKeys(fn ($policy) => [$policy->id => $policy->type->name.' · '.\App\Support\LeaveDays::withUnit($policy->default_days)])" required /><x-input name="event_date" label="Qualifying event date" type="date" required /><x-select name="event_variant" label="Verified entitlement variant" :options="['standard' => 'Standard', 'solo_parent' => 'Solo parent', 'miscarriage_or_emergency_termination' => 'Miscarriage / emergency termination']" required /><x-input name="verification_notes" label="Eligibility evidence and HR verification" required minlength="10" maxlength="2000" /><p class="small muted mb-4">The effective policy determines the granted days. This records HR verification; it does not infer legal eligibility.</p><x-button type="submit">Grant entitlement</x-button></div>
        </form>

        <form method="POST" class="card" action="{{ route('hr.leave.policies.store') }}" aria-labelledby="policy-heading" data-confirm-title="Save effective leave policy?" data-confirm-text="A new effective date preserves historical allocations and ledger entries." data-confirm-button="Save policy">
            @csrf<div class="card-heading"><h2 id="policy-heading">Effective-dated policy</h2></div>
            <div class="card-body"><x-select name="leave_type_id" label="Leave type" :options="$types->pluck('name', 'id')" required /><x-input name="effective_from" label="Effective from" type="date" required /><x-select name="allocation_basis" label="Allocation basis" :options="['annual' => 'Annual allocation', 'event' => 'Verified event']" required /><x-input name="default_days" label="Default entitlement days" type="number" step="0.5" min="0.5" max="366" required /><x-select name="day_basis" label="Day basis" :options="['working_days' => 'Working days', 'calendar_days' => 'Calendar days']" required /><x-input name="carryover_limit" label="Carryover limit (optional)" type="number" step="0.5" min="0" max="366" /><x-select name="proration_rule" label="Proration" :options="['none' => 'No proration', 'monthly' => 'Monthly proration']" required /><x-button type="submit">Save policy</x-button></div>
        </form>
    </div>

    <section class="card mt-6" aria-labelledby="policy-history-heading">
        <div class="card-heading"><h2 id="policy-history-heading">Policy history</h2></div>
        <div class="overflow-x-auto" tabindex="0" role="region" aria-label="Effective leave policy history">
            <x-table :columns="['Leave type', 'Effective dates', 'Basis', 'Days', 'Day count', 'Carryover', 'Status']" caption="Effective leave policy history">
                @foreach($policies as $policy)<tr><td>{{ $policy->type->name }}</td><td>{{ $policy->effective_from->format('M j, Y') }} – {{ $policy->effective_until?->format('M j, Y') ?? 'Open' }}</td><td>{{ ucfirst($policy->allocation_basis) }}</td><td>{{ \App\Support\LeaveDays::format($policy->default_days) }}</td><td>{{ str_replace('_', ' ', $policy->day_basis) }}</td><td>{{ $policy->carryover_limit === null ? 'Not set' : \App\Support\LeaveDays::format($policy->carryover_limit) }}</td><td><x-state :value="$policy->is_active ? 'active' : 'inactive'" /></td></tr>@endforeach
            </x-table>
        </div>
    </section>
</x-layouts.app>
