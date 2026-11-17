<x-layouts.app title="Employee Profile">
<x-page-header :title="$employee->full_name" description="Read-only Employee Profile">
@if($returnLeave)<x-button variant="secondary" :href="route('supervisor.leave.show', $returnLeave)">Back to leave application</x-button>@endif
<x-button variant="secondary" :href="route('supervisor.leave.index')">Back to Leave Management</x-button>
</x-page-header>
<section class="card"><x-employee-profile-header :employee="$employee" /><div class="card-heading"><h2>Work information</h2></div><x-employee-profile-details :employee="$employee" /></section>
</x-layouts.app>
