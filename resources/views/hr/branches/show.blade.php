<x-layouts.app title="Branch Details">
    <x-page-header :title="$branch->branch_name" :description="$branch->branch_code">
        <x-button :href="route('hr.branches.edit', $branch)">Edit branch</x-button>
    </x-page-header>
    <section class="card">
        <div class="card-heading"><h2>Office information</h2><x-state :value="$branch->status" /></div>
        <div class="card-body">
            @foreach(['Street address' => $branch->address, 'City / Province' => $branch->city.', '.$branch->province, 'Postal code' => $branch->postal_code ?? 'Not provided', 'Assigned employees' => $branch->current_assignments_count] as $label => $value)
                <div class="profile-row"><span class="muted">{{ $label }}</span><span>{{ $value }}</span></div>
            @endforeach
        </div>
        <div class="card-body border-t border-slate-100"><x-branch-location-map :branch="$branch" readonly /></div>
    </section>
    <div class="mt-6"><x-alert>Time In and Time Out use the saved location and radius for server-side GPS validation. Saved changes apply to new captures; historical attendance keeps its original geofence and verification result.</x-alert></div>
</x-layouts.app>
