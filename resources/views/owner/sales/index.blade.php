<x-owner-layout title="Transaksi">
    <form method="GET" action="{{ route('owner.sales.index') }}" class="mb-4 flex flex-wrap items-end justify-center gap-3">
        <div>
            <x-input-label for="from" value="Dari tanggal" />
            <x-text-input id="from" name="from" type="date" class="mt-1 text-sm" :value="$filters['from'] ?? ''" />
        </div>
        <div>
            <x-input-label for="to" value="Sampai tanggal" />
            <x-text-input id="to" name="to" type="date" class="mt-1 text-sm" :value="$filters['to'] ?? ''" />
        </div>
        <div>
            <x-input-label for="cashier" value="Kasir" />
            <x-select-input id="cashier" name="cashier" class="mt-1 text-sm">
                <option value="">Semua kasir</option>
                @foreach ($cashiers as $cashier)
                    <option value="{{ $cashier->id }}" @selected(($filters['cashier'] ?? null) == $cashier->id)>{{ $cashier->name }}</option>
                @endforeach
            </x-select-input>
        </div>
        <div>
            <x-input-label for="status" value="Status" />
            <x-select-input id="status" name="status" class="mt-1 text-sm">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-select-input>
        </div>
        <x-secondary-button type="submit">Terapkan</x-secondary-button>
        @if (array_filter($filters))
            <a href="{{ route('owner.sales.index') }}" class="py-2 text-sm text-stone-600 hover:underline">Reset</a>
        @endif
    </form>
    <x-input-error :messages="$errors->get('to')" class="mb-4" />

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Waktu</th>
                    <th class="px-4 py-3">Invoice</th>
                    <th class="px-4 py-3">Kasir</th>
                    <th class="px-4 py-3">Pembayaran</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($sales as $sale)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 tabular-nums text-stone-600">{{ $sale->created_at->translatedFormat('j M Y H:i') }}</td>
                        <td class="px-4 py-3 font-medium">{{ $sale->invoice_no }}</td>
                        <td class="px-4 py-3">{{ $sale->user->name }}</td>
                        <td class="px-4 py-3 text-stone-600">{{ $sale->payment_method->label() }}, {{ $sale->items_count }} menu</td>
                        <td @class(['px-4 py-3 text-right tabular-nums', 'text-stone-400 line-through' => $sale->isVoid()])>{{ rupiah($sale->total) }}</td>
                        <td class="px-4 py-3 text-center">
                            <span @class([
                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                'bg-emerald-100 text-emerald-800' => ! $sale->isVoid(),
                                'bg-red-100 text-red-700' => $sale->isVoid(),
                            ])>{{ $sale->status->label() }}</span>
                        </td>
                        <td class="px-4 py-3 text-center">
                            <a href="{{ route('owner.sales.show', $sale) }}" class="font-medium text-amber-700 hover:underline">Detail</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-10 text-center text-stone-500">Tidak ada transaksi yang cocok dengan filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $sales->links() }}</div>
</x-owner-layout>
