<section class="card order-4 min-w-0">
    <div class="card-heading px-5! py-3.5!"><h2>Review history</h2></div>
    <ol class="px-5 py-4">
        @foreach($leave->reviews as $review)
            <li class="relative ml-1.5 border-l border-slate-200 pb-4 pl-4 text-sm leading-5 wrap-anywhere last:border-transparent last:pb-0">
                <span class="absolute top-1.5 -left-1 size-2 rounded-full bg-(--limcoma-teal-dark)" aria-hidden="true"></span>
                <strong class="font-semibold text-slate-900">{{ ucwords(str_replace('_', ' ', $review->action)) }}</strong>
                <p class="mt-1 text-xs leading-5 text-slate-600">{{ $review->reviewed_at->timezone($timezone)->format('M j, Y H:i') }} · {{ $review->reviewer?->name ?? 'Scheduled routing' }}</p>
                @if(filled($review->remarks))
                    <p class="mt-1 text-sm whitespace-pre-wrap text-slate-700">{{ $review->remarks }}</p>
                @endif
                @if(array_key_exists('supervisor_id', $review->metadata ?? []))<p class="mt-1 text-xs leading-5 text-slate-600">Reviewer: {{ $review->metadata['previous_supervisor_id'] ?? 'None' }} → {{ $review->metadata['supervisor_id'] ?? 'Awaiting assignment' }}</p>
                @endif
            </li>
        @endforeach
    </ol>
</section>
