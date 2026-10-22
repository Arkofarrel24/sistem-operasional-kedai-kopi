<x-owner-layout title="Stok">
    <div class="mb-4 text-center">
        <p class="text-sm text-stone-600">Semua perubahan stok tercatat di riwayat pergerakan masing-masing bahan.</p>
        <div class="mt-2">
            <p class="text-sm text-stone-500">Total nilai stok</p>
            <p class="text-xl font-semibold tabular-nums">{{ rupiah($totalValue, whole: true) }}</p>
        </div>
    </div>

    {{-- Phones: one card per ingredient with its actions visible (the table needs sideways scrolling there). --}}
    <ul class="space-y-3 md:hidden">
        @forelse ($ingredients as $ingredient)
            @php($unit = $ingredient->unit->label())
            <li class="rounded-2xl border border-stone-200 bg-white p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <a href="{{ route('owner.stock.show', $ingredient) }}" class="font-medium hover:underline">{{ $ingredient->name }}</a>
                        @unless ($ingredient->is_active)
                            <span class="ml-1 rounded-full bg-stone-200 px-2 py-0.5 text-xs font-medium text-stone-600">Nonaktif</span>
                        @endunless
                        <p class="mt-0.5 text-sm tabular-nums text-stone-600">{{ qty($ingredient->current_stock) }} dari minimum {{ qty($ingredient->min_stock) }} {{ $unit }}</p>
                        <p class="text-xs tabular-nums text-stone-500">Nilai {{ rupiah($ingredient->stockValue(), whole: true) }}, {{ rupiah($ingredient->cost_per_unit) }} / {{ $unit }}</p>
                    </div>
                    <x-stock-status-badge :status="$ingredient->stockStatus()" />
                </div>
                <div class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    <a href="{{ route('owner.stock.restock.create', $ingredient) }}" class="font-medium text-amber-700 hover:underline">Restock</a>
                    <a href="{{ route('owner.stock.adjustment.create', $ingredient) }}" class="font-medium text-amber-700 hover:underline">Penyesuaian</a>
                    <a href="{{ route('owner.stock.waste.create', $ingredient) }}" class="font-medium text-amber-700 hover:underline">Waste</a>
                    <a href="{{ route('owner.stock.show', $ingredient) }}" class="font-medium text-stone-600 hover:underline">Riwayat</a>
                </div>
            </li>
        @empty
            <li class="rounded-2xl border border-stone-200 bg-white px-4 py-10 text-center text-sm text-stone-500">
                Belum ada bahan. <a href="{{ route('owner.ingredients.create') }}" class="font-medium text-amber-700 hover:underline">Tambah bahan</a>.
            </li>
        @endforelse
    </ul>

    <x-card class="hidden overflow-x-auto md:block">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Bahan</th>
                    <th class="px-4 py-3 text-right">Stok Saat Ini</th>
                    <th class="px-4 py-3 text-right">Stok Minimum</th>
                    <th class="px-4 py-3 text-center">Status</th>
                    <th class="px-4 py-3 text-right">Nilai Stok</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($ingredients as $ingredient)
                    @php($unit = $ingredient->unit->label())
                    <tr>
                        <td class="px-4 py-3">
                            <a href="{{ route('owner.stock.show', $ingredient) }}" class="font-medium hover:underline">{{ $ingredient->name }}</a>
                            @unless ($ingredient->is_active)
                                <span class="ml-1 rounded-full bg-stone-200 px-2 py-0.5 text-xs font-medium text-stone-600">Nonaktif</span>
                            @endunless
                            <p class="text-xs text-stone-500">{{ rupiah($ingredient->cost_per_unit) }} / {{ $unit }}</p>
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ qty($ingredient->current_stock) }} {{ $unit }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-stone-600">{{ qty($ingredient->min_stock) }} {{ $unit }}</td>
                        <td class="px-4 py-3 text-center"><x-stock-status-badge :status="$ingredient->stockStatus()" /></td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($ingredient->stockValue(), whole: true) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-center gap-3 whitespace-nowrap">
                                <a href="{{ route('owner.stock.restock.create', $ingredient) }}" class="font-medium text-amber-700 hover:underline">Restock</a>
                                <a href="{{ route('owner.stock.adjustment.create', $ingredient) }}" class="font-medium text-amber-700 hover:underline">Penyesuaian</a>
                                <a href="{{ route('owner.stock.waste.create', $ingredient) }}" class="font-medium text-amber-700 hover:underline">Waste</a>
                                <a href="{{ route('owner.stock.show', $ingredient) }}" class="font-medium text-stone-600 hover:underline">Riwayat</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-stone-500">
                            Belum ada bahan. <a href="{{ route('owner.ingredients.create') }}" class="font-medium text-amber-700 hover:underline">Tambah bahan</a>.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $ingredients->links() }}</div>
</x-owner-layout>
