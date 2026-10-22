<x-owner-layout :title="'Riwayat Stok: '.$ingredient->name">
    <x-slot:actions>
        <x-link-button href="{{ route('owner.stock.restock.create', $ingredient) }}">Restock</x-link-button>
    </x-slot:actions>

    <div class="mb-4">
        @include('owner.stock._summary')
    </div>

    <form novalidate method="GET" action="{{ route('owner.stock.show', $ingredient) }}" class="mb-4 flex flex-wrap items-end justify-center gap-3">
        <div>
            <x-input-label for="type" value="Jenis" />
            <x-select-input id="type" name="type" class="mt-1 text-sm">
                <option value="">Semua jenis</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected(($filters['type'] ?? null) === $type->value)>{{ $type->label() }}</option>
                @endforeach
            </x-select-input>
        </div>
        <div>
            <x-input-label for="from" value="Dari tanggal" />
            <x-text-input id="from" name="from" type="date" class="mt-1 text-sm" :value="$filters['from'] ?? ''" />
        </div>
        <div>
            <x-input-label for="to" value="Sampai tanggal" />
            <x-text-input id="to" name="to" type="date" class="mt-1 text-sm" :value="$filters['to'] ?? ''" />
        </div>
        <x-secondary-button type="submit">Terapkan</x-secondary-button>
        @if (array_filter($filters))
            <a href="{{ route('owner.stock.show', $ingredient) }}" class="py-2 text-sm text-stone-600 hover:underline">Reset</a>
        @endif
    </form>
    <x-input-error :messages="$errors->get('to')" class="mb-4" />

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Waktu</th>
                    <th class="px-4 py-3 text-center">Jenis</th>
                    <th class="px-4 py-3 text-right">Jumlah</th>
                    <th class="px-4 py-3 text-right">Harga / Satuan</th>
                    <th class="px-4 py-3">Dicatat oleh</th>
                    <th class="px-4 py-3">Transaksi / Catatan</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($movements as $movement)
                    @php($in = bccomp($movement->quantity, '0', 2) > 0)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3 tabular-nums text-stone-600">{{ $movement->created_at->translatedFormat('j M Y H:i') }}</td>
                        <td class="px-4 py-3 text-center">{{ $movement->type->label() }}</td>
                        <td @class(['whitespace-nowrap px-4 py-3 text-right font-medium tabular-nums', 'text-emerald-700' => $in, 'text-red-600' => ! $in])>
                            {{ $in ? '+' : '−' }}{{ qty(ltrim($movement->quantity, '-')) }} {{ $ingredient->unit->label() }}
                        </td>
                        <td class="whitespace-nowrap px-4 py-3 text-right tabular-nums text-stone-600">{{ $movement->unit_cost !== null ? rupiah($movement->unit_cost) : '–' }}</td>
                        <td class="px-4 py-3">{{ $movement->user->name }}</td>
                        <td class="px-4 py-3">
                            @if ($movement->sale)
                                <a href="{{ route('owner.sales.show', $movement->sale) }}" class="font-medium text-amber-700 hover:underline">{{ $movement->sale->invoice_no }}</a>
                            @endif
                            @if ($movement->note && ! $movement->sale)
                                <span class="text-stone-600">{{ $movement->note }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-stone-500">Tidak ada pergerakan stok yang cocok dengan filter.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $movements->links() }}</div>
</x-owner-layout>
