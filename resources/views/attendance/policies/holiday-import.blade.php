<x-layouts.app title="Import Philippine Holidays">
    <x-page-header title="Import Philippine Holidays" description="Transcribe a verified government declaration into CSV, then review and select the records to activate.">
        <x-button variant="secondary" :href="route('hr.holidays.index')">Holiday Management</x-button>
    </x-page-header>
    <section class="card mb-6" aria-labelledby="holiday-import-upload">
        <div class="card-heading"><h2 id="holiday-import-upload">Source and CSV file</h2></div>
        <form method="POST" enctype="multipart/form-data" action="{{ route('hr.holiday-import.preview') }}" class="card-body space-y-4">
            @csrf
            <x-form-errors />
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-input name="year" label="Applicable year" type="number" min="1900" max="2200" :value="now(config('attendance.timezone'))->year" required />
                <x-input name="source_url" label="Official government source (HTTPS)" type="url" maxlength="2048" required />
                <x-input name="declaration" label="Proclamation number or official reference" maxlength="255" required />
                <x-input name="csv" label="CSV file" type="file" accept=".csv,text/csv,text/plain" aria-describedby="holiday-csv-format" required />
            </div>
            <p id="holiday-csv-format" class="small muted rounded-lg border border-slate-200 bg-slate-50 p-4 leading-relaxed">Use the exact header <code>date,name,type,work_expected</code>. Use YYYY-MM-DD dates, a type of <code>regular</code>, <code>special_non_working</code> or <code>special_working</code>, and <code>1</code> for follow the schedule or <code>0</code> for non-working. Maximum 200 rows and 64 KB. Add local declarations in Holiday Management so you can select their scope.</p>
            <label class="flex gap-2"><input type="checkbox" name="verified" value="1" required> I verified these dates and classifications against the cited official source.</label>
            <x-button type="submit">Preview holidays</x-button>
        </form>
    </section>
    <section class="card">
        <div class="card-heading"><h2>Import history</h2></div>
        <x-table :columns="['Imported', 'Year', 'Official reference', 'Approved by account', 'Records']" caption="Approved holiday imports">
            @forelse($history as $import)
                <tr><td>{{ $import->created_at->timezone(config('attendance.timezone'))->format('M j, Y H:i') }}</td><td>{{ $import->year }}</td><td><a class="text-link" href="{{ $import->source_url }}">{{ $import->declaration }}</a></td><td>{{ $import->approved_by }}</td><td>{{ $import->imported_count }}</td></tr>
            @empty <tr><td colspan="5" class="muted">No approved imports.</td></tr> @endforelse
        </x-table>
        <div class="card-body">{{ $history->links() }}</div>
    </section>
</x-layouts.app>
