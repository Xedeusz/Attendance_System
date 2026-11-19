@props(['month', 'dayView', 'dayData'])
@php
    $start = $month->startOfWeek();
    $end = $month->endOfMonth()->endOfWeek()->startOfDay();
@endphp
<div class="overflow-x-auto" role="region" aria-label="{{ $month->format('F Y') }} calendar" tabindex="0">
    <div class="grid grid-cols-1 sm:min-w-[46rem] sm:grid-cols-7 rounded-lg border border-slate-200 text-sm">
        @foreach(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $weekday)
            <div class="hidden bg-slate-50 p-2 text-center sm:block font-semibold text-slate-700">{{ $weekday }}</div>
        @endforeach
        @for($day = $start; $day->lte($end); $day = $day->addDay())
            <div @class(['min-w-0 border-t border-slate-200 p-2 sm:min-h-24 sm:border-r', 'hidden sm:block' => $day->month !== $month->month]) @if($day->month !== $month->month) aria-hidden="true" @endif>
                @if($day->month === $month->month)
                    <time class="block font-semibold" datetime="{{ $day->toDateString() }}"><span class="sm:hidden">{{ $day->format('D, M j') }}</span><span class="hidden sm:inline">{{ $day->day }}</span></time>
                    @include($dayView, ['date' => $day->toDateString(), ...$dayData])
                @endif
            </div>
        @endfor
    </div>
</div>
