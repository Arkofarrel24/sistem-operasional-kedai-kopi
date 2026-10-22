<x-owner-layout title="Laporan">
    @php
        $period = $from->translatedFormat('j M Y').' sampai '.$to->translatedFormat('j M Y');
        $revenue = (float) $summary['revenue'];
        $methodColors = ['cash' => 'bg-amber-500', 'qris' => 'bg-stone-700', 'transfer' => 'bg-stone-400'];
        $percent = fn (float $part, float $whole) => $whole > 0 ? number_format($part / $whole * 100, 1, ',', '.') : '0,0';
        $cogsShare = $revenue > 0 ? min(100, (float) $profit['cogs'] / $revenue * 100) : 0;
        $maxWaste = $waste['items']->max(fn ($item) => (float) $item->value) ?: 1;
        $maxProduct = $topProducts->max(fn ($product) => (float) $product->revenue) ?: 1;
        $card = 'rounded-2xl border border-stone-200 bg-white shadow-[0_1px_2px_rgba(28,25,23,0.04)]';
    @endphp

    <div class="mx-auto max-w-6xl space-y-6">
        {{-- Range --}}
        <form novalidate method="GET" action="{{ route('owner.reports.index') }}"
              class="{{ $card }} flex flex-wrap items-end justify-center gap-3 px-6 py-4">
            <div>
                <x-input-label for="from" value="Dari tanggal" />
                <x-text-input id="from" name="from" type="date" class="mt-1 h-10 text-sm" :value="$from->toDateString()" />
            </div>
            <div>
                <x-input-label for="to" value="Sampai tanggal" />
                <x-text-input id="to" name="to" type="date" class="mt-1 h-10 text-sm" :value="$to->toDateString()" />
            </div>
            <x-secondary-button type="submit">Tampilkan</x-secondary-button>
            <x-link-button href="{{ route('owner.reports.export', ['from' => $from->toDateString(), 'to' => $to->toDateString()]) }}">
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.75 2.75a.75.75 0 0 0-1.5 0v8.614L6.295 8.235a.75.75 0 1 0-1.09 1.03l4.25 4.5a.75.75 0 0 0 1.09 0l4.25-4.5a.75.75 0 0 0-1.09-1.03l-2.955 3.129V2.75Z" /><path d="M3.5 12.75a.75.75 0 0 0-1.5 0v2.5A2.75 2.75 0 0 0 4.75 18h10.5A2.75 2.75 0 0 0 18 15.25v-2.5a.75.75 0 0 0-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5Z" /></svg>
                Ekspor Excel
            </x-link-button>
        </form>
        <x-input-error :messages="$errors->get('from')" class="text-center" />
        <x-input-error :messages="$errors->get('to')" class="text-center" />

        {{-- Summary --}}
        <section aria-labelledby="summary-title" class="{{ $card }}">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-stone-100 px-6 py-4">
                <h2 id="summary-title" class="text-base font-semibold">Rekap penjualan</h2>
                <p class="text-sm text-stone-500">{{ $period }}, tanpa transaksi void</p>
            </div>

            <dl class="grid divide-y divide-stone-100 md:grid-cols-[1.4fr_1fr_1fr] md:divide-x md:divide-y-0">
                <x-stat label="Omzet" :value="rupiah($summary['revenue'])" lead :i="0" />
                <x-stat label="Transaksi" :value="number_format($summary['transactions'], 0, ',', '.')" :i="1" />
                <x-stat label="Rata-rata per transaksi" :value="rupiah($summary['average'], whole: true)" :i="2" />
            </dl>

            <div class="border-t border-stone-100 px-6 py-5">
                <h3 class="text-sm font-semibold">Per metode pembayaran</h3>
                @if ($summary['transactions'] === 0)
                    <p class="mt-3 text-sm text-stone-600">Belum ada transaksi pada periode ini. Coba pilih rentang tanggal lain.</p>
                @else
                <div class="grow-x mt-3 flex h-3 gap-0.5 overflow-hidden rounded-full" style="--i: 2" aria-hidden="true">
                    @foreach ($summary['by_method'] as $row)
                        @if ((float) $row['revenue'] > 0)
                            <span class="{{ $methodColors[$row['method']->value] }}" style="width: {{ round((float) $row['revenue'] / $revenue * 100, 2) }}%"></span>
                        @endif
                    @endforeach
                </div>
                <table class="mt-3 w-full text-sm">
                    <thead class="sr-only">
                        <tr><th>Metode</th><th>Transaksi</th><th>Omzet</th><th>Porsi</th></tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach ($summary['by_method'] as $row)
                            <tr>
                                <td class="py-2.5">
                                    <span class="mr-2 inline-block h-2.5 w-2.5 rounded-full align-middle {{ $methodColors[$row['method']->value] }}" aria-hidden="true"></span>{{ $row['method']->label() }}
                                </td>
                                <td class="py-2.5 text-right tabular-nums text-stone-600">{{ number_format($row['transactions'], 0, ',', '.') }} transaksi</td>
                                <td class="py-2.5 text-right font-medium tabular-nums">{{ rupiah($row['revenue']) }}</td>
                                <td class="w-20 py-2.5 text-right tabular-nums text-stone-500">{{ $percent((float) $row['revenue'], $revenue) }}%</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                @endif
            </div>
        </section>

        <div class="grid gap-6 lg:grid-cols-2">
            {{-- Gross profit --}}
            <section aria-labelledby="profit-title" class="{{ $card }} p-6">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 id="profit-title" class="text-base font-semibold">Laba kotor</h2>
                    <p class="text-sm text-stone-500">Margin
                        <span class="ml-1 text-base font-bold tabular-nums text-stone-900">{{ $profit['margin'] !== null ? number_format((float) $profit['margin'] * 100, 1, ',', '.').'%' : '-' }}</span>
                    </p>
                </div>

                {{-- Revenue split into the cost of ingredients sold and what is left as gross profit. --}}
                @if ($revenue > 0)
                    <div class="grow-x mt-4 flex h-3 gap-0.5 overflow-hidden rounded-full bg-stone-100" style="--i: 1" aria-hidden="true">
                        <span class="bg-stone-400" style="width: {{ round($cogsShare, 2) }}%"></span>
                        <span class="bg-amber-500" style="width: {{ round(100 - $cogsShare, 2) }}%"></span>
                    </div>
                @else
                    <p class="mt-4 text-sm text-stone-600">Belum ada penjualan pada periode ini, jadi belum ada laba kotor.</p>
                @endif

                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-600">Omzet</dt>
                        <dd class="tabular-nums">{{ rupiah($profit['revenue']) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-stone-600"><span class="mr-2 inline-block h-2.5 w-2.5 rounded-full bg-stone-400 align-middle" aria-hidden="true"></span>HPP (biaya bahan terjual)</dt>
                        <dd class="tabular-nums">−{{ rupiah($profit['cogs'], whole: true) }}</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4 border-t border-stone-200 pt-3">
                        <dt class="font-semibold"><span class="mr-2 inline-block h-2.5 w-2.5 rounded-full bg-amber-500 align-middle" aria-hidden="true"></span>Laba kotor</dt>
                        <dd class="reveal text-2xl font-bold tabular-nums" style="--i: 3">{{ rupiah($profit['gross_profit'], whole: true) }}</dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-stone-500">HPP memakai harga beli bahan saat transaksi terjadi, jadi tidak berubah walaupun harga bahan naik setelahnya.</p>
            </section>

            {{-- Waste --}}
            <section aria-labelledby="waste-title" class="{{ $card }} p-6">
                <div class="flex items-baseline justify-between gap-4">
                    <h2 id="waste-title" class="text-base font-semibold">Nilai waste</h2>
                    <p class="reveal text-2xl font-bold tabular-nums" style="--i: 3">{{ rupiah($waste['total'], whole: true) }}</p>
                </div>

                @if ($waste['items']->isEmpty())
                    <p class="mt-4 text-sm text-stone-600">Tidak ada bahan yang dicatat terbuang pada periode ini.</p>
                @else
                    <ul class="mt-4 space-y-3 text-sm">
                        @foreach ($waste['items'] as $item)
                            <li>
                                <div class="flex justify-between gap-4">
                                    <span>{{ $item->name }} <span class="text-stone-500">{{ qty($item->quantity) }} {{ $item->unit }}</span></span>
                                    <span class="tabular-nums">{{ rupiah($item->value, whole: true) }}</span>
                                </div>
                                <span class="mt-1.5 block h-1.5" aria-hidden="true">
                                    <span class="grow-x block h-full rounded-full bg-red-300" style="width: {{ round((float) $item->value / $maxWaste * 100, 1) }}%; --i: {{ $loop->index }}"></span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-4 text-xs text-stone-500">Dinilai dengan harga beli bahan saat waste dicatat.</p>
            </section>
        </div>

        {{-- Per hour --}}
        <section aria-labelledby="hourly-title" class="{{ $card }} p-6">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="hourly-title" class="text-base font-semibold">Transaksi per jam</h2>
                @if ($busiestHours)
                    <p class="flex items-center gap-2 text-sm text-stone-600">
                        <span class="inline-block h-2.5 w-2.5 rounded-sm bg-amber-700" aria-hidden="true"></span>
                        Paling ramai pukul {{ collect($busiestHours)->map(fn ($hour) => sprintf('%02d.00', $hour))->implode(', ') }}
                    </p>
                @endif
            </div>
            @if ($summary['transactions'] === 0)
                <p class="mt-4 rounded-xl bg-stone-50 px-4 py-10 text-center text-sm text-stone-600">Belum ada transaksi pada periode ini, jadi jam ramai belum bisa dihitung.</p>
            @else
                <div class="relative mt-5 h-64">
                    <canvas data-hourly-chart='@json($hourly)' data-busiest='@json($busiestHours)'
                            role="img" aria-label="Grafik jumlah transaksi per jam, {{ $period }}"></canvas>
                </div>
            @endif
        </section>

        {{-- Best sellers --}}
        <section aria-labelledby="top-title" class="{{ $card }} overflow-hidden">
            <h2 id="top-title" class="px-6 pb-3 pt-5 text-base font-semibold">Menu terlaris</h2>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="border-y border-stone-100 bg-stone-50/70 text-left text-xs font-medium text-stone-500">
                        <tr>
                            <th class="w-12 px-6 py-2.5 text-center">#</th>
                            <th class="px-4 py-2.5">Menu</th>
                            <th class="px-4 py-2.5 text-right">Terjual</th>
                            <th class="px-4 py-2.5 text-right">Omzet</th>
                            <th class="w-56 px-6 py-2.5">Porsi omzet</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @forelse ($topProducts as $product)
                            <tr>
                                <td class="px-6 py-3 text-center tabular-nums text-stone-400">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3 font-medium">{{ $product->name }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ number_format($product->quantity, 0, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($product->revenue) }}</td>
                                <td class="px-6 py-3">
                                    <div class="flex items-center gap-3">
                                        <span class="block h-1.5 flex-1" aria-hidden="true">
                                            <span @class(['grow-x block h-full rounded-full', 'bg-amber-500' => $loop->first, 'bg-amber-300' => ! $loop->first])
                                                  style="width: {{ round((float) $product->revenue / $maxProduct * 100, 1) }}%; --i: {{ min($loop->index, 10) }}"></span>
                                        </span>
                                        <span class="w-12 text-right tabular-nums text-stone-600">{{ $percent((float) $product->revenue, $revenue) }}%</span>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-stone-500">Belum ada penjualan pada periode ini.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @push('scripts')
        @vite('resources/js/charts.js')
    @endpush
</x-owner-layout>
