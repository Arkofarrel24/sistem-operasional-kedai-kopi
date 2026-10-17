<x-pos-layout>
    <div class="h-full overflow-y-auto px-4 py-6 sm:px-6">
        <div class="mx-auto max-w-3xl">
            <h1 class="text-xl font-semibold">Riwayat hari ini</h1>
            <p class="mt-1 text-base text-stone-600">Transaksi yang Anda catat pada {{ today()->translatedFormat('j M Y') }}.</p>

            @if ($sales->isEmpty())
                <div class="mt-6 rounded-2xl bg-white p-10 text-center ring-1 ring-inset ring-stone-200">
                    <p class="text-base text-stone-600">Belum ada transaksi hari ini.</p>
                    <a href="{{ route('pos.index') }}" class="mt-4 inline-flex h-12 items-center rounded-xl bg-stone-900 px-6 text-base font-semibold text-white active:bg-stone-700">Mulai transaksi</a>
                </div>
            @else
                <ul class="mt-6 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white ring-1 ring-inset ring-stone-200">
                    @foreach ($sales as $sale)
                        <li class="flex items-center gap-4 px-4 py-3">
                            <div class="w-14 shrink-0 text-base font-semibold tabular-nums">{{ $sale->created_at->format('H:i') }}</div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-base font-medium">
                                    {{ $sale->invoice_no }}
                                    @if ($sale->isVoid())
                                        <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700">Void</span>
                                    @endif
                                </p>
                                <p class="text-sm text-stone-500">{{ $sale->items_count }} menu, {{ $sale->payment_method->label() }}</p>
                            </div>
                            <p @class(['shrink-0 text-base font-semibold tabular-nums', 'text-stone-400 line-through' => $sale->isVoid()])>{{ rupiah($sale->total) }}</p>
                            <a href="{{ route('pos.sales.show', $sale) }}"
                               class="flex h-11 shrink-0 items-center rounded-xl px-4 text-base font-medium text-stone-800 ring-1 ring-inset ring-stone-300 active:bg-stone-100">Struk</a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-pos-layout>
