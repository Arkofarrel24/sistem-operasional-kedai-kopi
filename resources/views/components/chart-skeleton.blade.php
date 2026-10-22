{{-- Placeholder bars under a chart until Chart.js has drawn it (charts.js removes [data-chart-skeleton]). --}}
<div data-chart-skeleton aria-hidden="true" class="pointer-events-none absolute inset-x-8 bottom-6 top-4 flex items-end gap-2 motion-safe:animate-pulse">
    @foreach ([35, 55, 45, 70, 60, 80, 50, 65, 40, 75, 55, 45] as $height)
        <span class="flex-1 rounded-t-md bg-stone-100" style="height: {{ $height }}%"></span>
    @endforeach
</div>
