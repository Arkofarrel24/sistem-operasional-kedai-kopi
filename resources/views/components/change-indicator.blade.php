@props(['change', 'previous'])

{{--
    Change versus last week: a chip (arrow + percentage) followed by last week's value, on one
    centred line. $change is a fraction string ("0.125" = +12,5 %) or null when there is nothing to compare.
--}}
<div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm leading-5">
    @if ($change === null)
        <span class="text-stone-500">Minggu lalu belum ada transaksi</span>
    @else
        @php
            $direction = bccomp($change, '0', 4);
            $percent = number_format(abs((float) $change) * 100, 1, ',', '.');
        @endphp
        <span @class([
            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 font-semibold tabular-nums',
            'bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-600/15' => $direction > 0,
            'bg-red-50 text-red-700 ring-1 ring-inset ring-red-600/15' => $direction < 0,
            'bg-stone-100 text-stone-600 ring-1 ring-inset ring-stone-500/15' => $direction === 0,
        ])>
            @if ($direction > 0)
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 3a.75.75 0 0 1 .53.22l5 5a.75.75 0 1 1-1.06 1.06L10.75 5.56V16a.75.75 0 0 1-1.5 0V5.56L5.53 9.28a.75.75 0 0 1-1.06-1.06l5-5A.75.75 0 0 1 10 3Z" clip-rule="evenodd" /></svg>
                <span class="sr-only">Naik</span>
            @elseif ($direction < 0)
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 17a.75.75 0 0 1-.53-.22l-5-5a.75.75 0 1 1 1.06-1.06l3.72 3.72V4a.75.75 0 0 1 1.5 0v10.44l3.72-3.72a.75.75 0 1 1 1.06 1.06l-5 5A.75.75 0 0 1 10 17Z" clip-rule="evenodd" /></svg>
                <span class="sr-only">Turun</span>
            @else
                <span class="sr-only">Sama</span>
            @endif
            {{ $percent }}%
        </span>
        <span class="text-stone-500">minggu lalu <span class="tabular-nums">{{ $previous }}</span></span>
    @endif
</div>
