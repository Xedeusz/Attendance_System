<x-layouts.app :title="$branch->exists ? 'Edit Branch' : 'Add Branch'">
    <x-page-header :title="$branch->exists ? 'Edit Branch' : 'Add Branch'" description="Manage official office information and the attendance boundary." />
    <form method="POST" class="card" data-original-status="{{ $branch->status }}" action="{{ $branch->exists ? route('hr.branches.update', $branch) : route('hr.branches.store') }}">
        @csrf <x-edit-version :record="$branch" />
        @if($branch->exists) @method('PUT') @endif
        <div id="branch-location-geofencing" class="card-body"><x-branch-location-map :branch="$branch" /></div>
        <section id="branch-information" class="border-t border-slate-100" aria-label="Branch Information">
            <div class="card-heading"><h2>Branch Information</h2></div>
            <div class="card-body form-grid">
                @foreach(['branch_code' => 'Branch code', 'branch_name' => 'Branch name', 'address' => 'Street address', 'city' => 'City', 'province' => 'Province'] as $field => $label)
                    <x-input :name="$field" :label="$label" :value="$branch->$field" required />
                @endforeach
                <x-input name="postal_code" label="Postal code (optional)" :value="$branch->postal_code" maxlength="20" />
                <x-select name="status" label="Branch status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$branch->status" :placeholder="null" required />
            </div>
        </section>
        <div class="card-body border-t border-slate-100 space-y-5">
            @if($branch->exists)<label class="flex items-start gap-2 small muted"><input name="confirmation" type="checkbox" value="1" class="h-4 w-4">I confirm deactivation if I set this branch to inactive. Active employees must first be reassigned or marked inactive.</label>@endif
            <div class="flex gap-3 flex-wrap">
                <x-button type="submit">Save branch</x-button>
                <x-button :href="$branch->exists ? route('hr.branches.show', $branch) : route('hr.branches.index')" variant="secondary">Cancel</x-button>
            </div>
        </div>
    </form>
</x-layouts.app>
