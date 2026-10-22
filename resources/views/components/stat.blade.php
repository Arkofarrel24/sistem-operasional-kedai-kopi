@props(['label', 'value', 'lead' => false, 'i' => 0])

{{-- One key figure in a stats panel; the lead figure is set larger. Optional slot below the value. --}}
<div class="px-6 py-5">
    <dt class="text-sm text-stone-600">{{ $label }}</dt>
    <dd @class([
        'reveal mt-2 font-bold leading-none tracking-tight tabular-nums text-stone-900',
        'text-4xl sm:text-[2.75rem]' => $lead,
        'text-3xl' => ! $lead,
    ]) style="--i: {{ $i }}">{{ $value }}</dd>
    @if ($slot->isNotEmpty())
        <dd class="mt-3">{{ $slot }}</dd>
    @endif
</div>
