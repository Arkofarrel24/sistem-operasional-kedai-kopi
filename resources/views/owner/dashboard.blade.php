<x-owner-layout title="Beranda">
    @php
        $comparedAt = $now->subWeek()->translatedFormat('l, j M').' sampai pukul '.$now->format('H.i');
        $maxTop = $topProducts->max(fn ($product) => (float) $product->revenue) ?: 1;
        $lastSeven = array_slice($chart, -7);
        $sevenTotal = array_sum(array_column($lastSeven, 'revenue'));
    @endphp

    <div class="max-w-6xl space-y-6">
        {{-- Today --}}
        <section aria-labelledby="today-title" class="rounded-2xl border border-stone-200 bg-white shadow-[0_1px_2px_rgba(28,25,23,0.04)]">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-stone-100 px-6 py-4">
                <h2 id="today-title" class="text-base font-semibold">Hari ini, {{ $now->translatedFormat('l, j M Y') }}</h2>
                <p class="text-sm text-stone-500">Dibandingkan {{ $comparedAt }}</p>
            </div>

            <dl class="grid divide-y divide-stone-100 md:grid-cols-[1.4fr_1fr_1fr] md:divide-x md:divide-y-0">
                <x-stat label="Omzet" :value="rupiah($today['revenue'])" lead :i="0">
                    <x-change-indicator :change="$changes['revenue']" :previous="rupiah($lastWeek['revenue'])" />
                </x-stat>
                <x-stat label="Transaksi" :value="number_format($today['transactions'], 0, ',', '.')" :i="1">
                    <x-change-indicator :change="$changes['transactions']" :previous="number_format($lastWeek['transactions'], 0, ',', '.')" />
                </x-stat>
                <x-stat label="Rata-rata per transaksi" :value="rupiah($today['average'], whole: true)" :i="2">
                    <x-change-indicator :change="$changes['average']" :previous="rupiah($lastWeek['average'], whole: true)" />
                </x-stat>
            </dl>
        </section>

        <div class="grid gap-6 lg:grid-cols-3">
            {{-- Daily revenue --}}
            <section aria-labelledby="chart-title" class="rounded-2xl border border-stone-200 bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.04)] lg:col-span-2"
                     data-revenue-chart='@json($chart)'>
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="chart-title" class="text-base font-semibold">Omzet harian</h2>
                        <p class="mt-1 text-sm text-stone-600" aria-live="polite">
                            <span data-range-total class="font-semibold tabular-nums text-stone-900">{{ rupiah($sevenTotal, whole: true) }}</span>
                            dalam <span data-range-days>7</span> hari, rata-rata
                            <span data-range-average class="tabular-nums">{{ rupiah($sevenTotal / max(count($lastSeven), 1), whole: true) }}</span> per hari
                        </p>
                    </div>

                    {{-- The whole control is clickable; the white thumb slides to the chosen range. --}}
                    <div class="segmented relative grid grid-cols-2 rounded-lg bg-stone-100 p-1" role="radiogroup" aria-label="Rentang grafik" data-segmented data-selected="0">
                        <span class="segmented-thumb pointer-events-none absolute inset-y-1 left-1 w-[calc(50%-0.25rem)] rounded-md bg-white shadow-sm ring-1 ring-stone-900/5" aria-hidden="true"></span>
                        @foreach ([7, 30] as $index => $range)
                            <button type="button" role="radio" data-range="{{ $range }}"
                                    aria-checked="{{ $index === 0 ? 'true' : 'false' }}" tabindex="{{ $index === 0 ? '0' : '-1' }}"
                                    class="press relative h-9 min-w-[5.5rem] rounded-md px-4 text-sm font-medium text-stone-500 aria-checked:text-stone-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-500">
                                {{ $range }} hari
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="relative mt-5 h-64">
                    <x-chart-skeleton />
                    <canvas role="img" aria-label="Grafik omzet harian. Angkanya ada di tabel setelah grafik."></canvas>
                    {{-- Shown when the selected range has no sales (toggled by charts.js). --}}
                    <div data-chart-empty @class(['absolute inset-0 flex items-center justify-center bg-white/85 px-6 text-center', 'hidden' => $sevenTotal > 0])>
                        <p class="max-w-xs text-sm text-stone-600">
                            Belum ada penjualan dalam <span data-range-days-empty>7</span> hari terakhir. Grafik terisi setelah kasir mencatat transaksi.
                        </p>
                    </div>
                </div>

                {{-- The same numbers for screen readers (the canvas itself is only an image). --}}
                <table class="sr-only">
                    <caption>Omzet harian {{ count($chart) }} hari terakhir</caption>
                    <thead><tr><th scope="col">Tanggal</th><th scope="col">Omzet</th><th scope="col">Transaksi</th></tr></thead>
                    <tbody>
                        @foreach ($chart as $day)
                            <tr><td>{{ $day['label'] }}</td><td>{{ rupiah($day['revenue'], whole: true) }}</td><td>{{ $day['transactions'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </section>

            {{-- Low stock --}}
            <section aria-labelledby="low-stock-title" class="rounded-2xl border border-stone-200 bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.04)]">
                <div class="flex items-baseline justify-between gap-3">
                    <h2 id="low-stock-title" class="text-base font-semibold">Perlu restock</h2>
                    @if ($lowStock->isNotEmpty())
                        <span class="text-sm tabular-nums text-stone-500">{{ $lowStock->count() }} bahan</span>
                    @endif
                </div>

                @if ($lowStock->isEmpty())
                    <p class="mt-4 text-sm text-stone-600">Semua bahan di atas stok minimum.</p>
                @else
                    <ul class="mt-2 divide-y divide-stone-100">
                        @foreach ($lowStock as $ingredient)
                            @php
                                $unit = $ingredient->unit->label();
                                $status = $ingredient->stockStatus();
                                $fill = (float) $ingredient->min_stock > 0 ? min(100, max(0, (float) $ingredient->current_stock / (float) $ingredient->min_stock * 100)) : 0;
                            @endphp
                            <li class="py-3">
                                <div class="flex items-center justify-between gap-3">
                                    <p class="truncate font-medium">{{ $ingredient->name }}</p>
                                    <x-stock-status-badge :status="$status" />
                                </div>
                                <div class="mt-2 flex items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        {{-- Meter: stock on hand as a share of the minimum. --}}
                                        <div class="h-1.5 overflow-hidden rounded-full bg-stone-100" role="meter" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ round($fill) }}"
                                             aria-label="Stok {{ $ingredient->name }} terhadap minimum">
                                            <div @class(['grow-x h-full rounded-full', 'bg-red-500' => $status === \App\Enums\StockStatus::Out, 'bg-amber-500' => $status !== \App\Enums\StockStatus::Out])
                                                 style="width: {{ round($fill, 1) }}%; --i: {{ $loop->index }}"></div>
                                        </div>
                                        <p class="mt-1.5 text-xs tabular-nums text-stone-500">{{ qty($ingredient->current_stock) }} dari minimum {{ qty($ingredient->min_stock) }} {{ $unit }}</p>
                                    </div>
                                    <a href="{{ route('owner.stock.restock.create', $ingredient) }}"
                                       class="press shrink-0 rounded-lg px-3 py-1.5 text-sm font-semibold text-amber-800 ring-1 ring-inset ring-amber-300 hover:bg-amber-50">Restock</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- Best sellers this week --}}
        <section aria-labelledby="top-title" class="rounded-2xl border border-stone-200 bg-white p-6 shadow-[0_1px_2px_rgba(28,25,23,0.04)]">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="top-title" class="text-base font-semibold">Menu terlaris minggu ini</h2>
                <p class="text-sm text-stone-500">{{ $weekStart->translatedFormat('j M') }} sampai {{ $now->translatedFormat('j M Y') }}</p>
            </div>

            @if ($topProducts->isEmpty())
                <p class="mt-4 text-sm text-stone-600">Belum ada penjualan minggu ini.</p>
            @else
                <ol class="mt-5 space-y-4">
                    @foreach ($topProducts as $product)
                        <li class="grid grid-cols-[1.75rem_minmax(0,1fr)_auto] items-center gap-x-3 gap-y-1.5">
                            <span class="text-sm font-semibold tabular-nums text-stone-400">{{ $loop->iteration }}</span>
                            <span class="truncate font-medium">{{ $product->name }}</span>
                            <span class="text-right tabular-nums">
                                <span class="font-semibold">{{ rupiah($product->revenue) }}</span>
                                <span class="ml-2 text-sm text-stone-500">{{ number_format($product->quantity, 0, ',', '.') }} porsi</span>
                            </span>
                            <span></span>
                            <span class="col-span-2 block h-2" aria-hidden="true">
                                <span @class(['grow-x block h-full rounded-full', 'bg-amber-500' => $loop->first, 'bg-amber-300' => ! $loop->first])
                                      style="width: {{ round((float) $product->revenue / $maxTop * 100, 1) }}%; --i: {{ $loop->index }}"></span>
                            </span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>

    @push('scripts')
        @vite('resources/js/charts.js')
    @endpush
</x-owner-layout>
