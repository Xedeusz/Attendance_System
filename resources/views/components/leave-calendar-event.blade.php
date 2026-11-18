<button type="button" class="flex w-full min-w-0 items-start justify-between gap-3 rounded-lg border border-slate-200 px-3 py-2.5 text-left hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-[#2F1B72]"
        @click="openDetails(entry.id)">
    <span class="min-w-0">
        <span class="block font-semibold break-words text-slate-900" x-text="entry.title"></span>
        <span class="mt-1 block text-xs text-slate-600" x-text="dates(entry.start, entry.end)"></span>
        <span x-show="entry.half_day" class="mt-0.5 block text-xs text-slate-600" x-text="halfDay(entry)"></span>
    </span>
    <span class="max-w-[45%] shrink-0 rounded-full px-2 py-1 text-xs font-semibold" :class="statusColor(entry.status)" x-text="statuses[entry.status]"></span>
</button>
