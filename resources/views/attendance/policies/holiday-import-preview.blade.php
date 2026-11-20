<x-layouts.app title="Review Holiday Import">
    <x-page-header title="Review Holiday Import" :description="$data['declaration']">
        <x-button variant="secondary" :href="route('hr.holiday-import.index')">Start a new preview</x-button>
    </x-page-header>
    <x-form-errors />
    <form method="POST" action="{{ route('hr.holiday-import.store') }}" class="card" x-data='{ selectAll() { this.$root.querySelectorAll("input[name=\"selected[]\"]:not(:disabled)").forEach((checkbox) => checkbox.checked = true); } }'>
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <div class="card-body flex flex-wrap items-center justify-between gap-3">
            <a class="text-link" href="{{ $data['source_url'] }}">Open cited government declaration</a>
            <x-button type="button" variant="secondary" @click="selectAll()">Select all</x-button>
        </div>
        <x-table :columns="['Select', 'Date', 'Name', 'Classification', 'Work expected', 'Review']" caption="Proposed holiday imports">
            @foreach($rows as $index => $row)
                <tr>
                    <td><input type="checkbox" name="selected[]" value="{{ $index }}" aria-label="Import {{ $row['name'] }} on {{ $row['holiday_date'] }}" @disabled($statuses[$index]['duplicate']) @checked(in_array($index, (array) old('selected', [])))></td>
                    <td>{{ $row['holiday_date'] }}</td><th scope="row">{{ $row['name'] }}</th><td><x-attendance-holiday-badge :type="$row['type']" /></td><td>{{ $row['work_expected'] ? 'Follow schedule' : 'Non-working' }}</td>
                    <td>{{ $statuses[$index]['duplicate'] ? 'Duplicate / existing declaration' : ($statuses[$index]['conflict'] ? 'Overlapping classification or work expectation' : 'Ready to review') }}</td>
                </tr>
            @endforeach
        </x-table>
        <div class="card-body space-y-3">
            @if(collect($statuses)->contains('conflict', true))<label class="flex gap-2"><input type="checkbox" name="allow_conflicts" value="1" @checked(old('allow_conflicts'))> I reviewed the overlaps and approve keeping the selected declarations alongside existing records.</label>@endif
            <label class="flex gap-2"><input type="checkbox" name="confirm" value="1" required> Activate only the selected holidays.</label>
            <x-button type="submit">Confirm import</x-button>
        </div>
    </form>
</x-layouts.app>
