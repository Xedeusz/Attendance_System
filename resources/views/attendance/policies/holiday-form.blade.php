@php($title = $holiday->exists ? 'Edit holiday' : 'Add holiday')
<x-layouts.app :title="$title">
    <x-page-header :title="$title" description="Record the declaration, work expectation, and branch coverage.">
        <x-button variant="secondary" :href="route('hr.holidays.index')">Holiday Management</x-button>
    </x-page-header>
    <section class="card">
        <form method="POST" action="{{ $holiday->exists ? route('hr.holidays.update', $holiday) : route('hr.holidays.store') }}"
              class="card-body space-y-3" x-data="{ scope: @js(old('scope', $holiday->scope ?? 'company')) }">
            @csrf
            @if($holiday->exists) @method('PUT') @endif
            <x-form-errors />
            <fieldset class="grid min-w-0 gap-x-5 sm:grid-cols-2">
                <legend class="mb-3 text-base font-semibold">Holiday and work expectation</legend>
                <x-input name="name" label="Holiday name" :value="$holiday->name" maxlength="150" required />
                <x-input name="holiday_date" label="Holiday date (determines calendar year)" type="date" :value="$holiday->holiday_date?->toDateString()" required />
                <x-select name="type" label="Holiday classification" :options="\App\Models\AttendanceHoliday::TYPES" :value="$holiday->type" required />
                <x-select name="work_expected" label="Normal work expectation" :options="[0 => 'Non-working unless a shift is approved', 1 => 'Follow assigned work schedule']" :value="$holiday->exists ? (int)$holiday->work_expected : null" required />
                <x-select name="status" label="Status" :options="\App\Models\AttendanceHoliday::STATUSES" :value="$holiday->status ?? 'active'" required />
            </fieldset>
            <fieldset class="grid min-w-0 gap-x-5 border-t border-slate-200 pt-3 sm:grid-cols-2">
                <legend class="pr-2 text-base font-semibold">Official reference</legend>
                <x-input name="declaration" label="Official declaration / reference" :value="$holiday->declaration" maxlength="255" />
                <x-input name="proclamation_number" label="Government proclamation number" :value="$holiday->proclamation_number" maxlength="100" />
                <x-input name="source_url" label="Source URL" type="url" :value="$holiday->source_url" maxlength="2048" />
                <x-input name="description" label="Description" :value="$holiday->description" maxlength="2000" />
            </fieldset>
            <fieldset class="min-w-0 space-y-3 border-t border-slate-200 pt-3">
                <legend class="pr-2 text-base font-semibold">Holiday coverage</legend>
                <x-select name="scope" label="Applicability" :options="['company' => 'Company-wide', 'branches' => 'Selected branches', 'locations' => 'Selected cities and provinces']" :placeholder="null" x-model="scope" />
                <fieldset x-show="scope === 'branches'" class="space-y-2">
                    <legend class="field-label">Covered branches</legend>
                    @foreach($branches as $id => $name)
                        <label class="flex items-center gap-2"><input type="checkbox" name="branch_ids[]" value="{{ $id }}" @checked(in_array($id, (array) old('branch_ids', $holiday->exists ? $holiday->branches->modelKeys() : [])))> {{ $name }}</label>
                    @endforeach
                </fieldset>
                <fieldset x-show="scope === 'locations'" class="space-y-2">
                    <legend class="field-label">Covered locations</legend>
                    @foreach($locations as $location => $label)
                        <label class="flex items-center gap-2"><input type="checkbox" name="locations[]" value="{{ $location }}" @checked(in_array($location, (array) old('locations', $holiday->locations ?? []), true))> {{ $label }}</label>
                    @endforeach
                </fieldset>
            </fieldset>
            <p class="small muted">When active declarations overlap, a non-working declaration removes the work obligation unless HR has approved a shift. Each declaration remains visible.</p>
            <x-button type="submit">Save holiday</x-button>
        </form>
    </section>
</x-layouts.app>
