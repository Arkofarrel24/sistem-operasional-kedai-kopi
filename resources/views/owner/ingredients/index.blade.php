<x-owner-layout title="Bahan">
    <x-slot:actions>
        <x-link-button href="{{ route('owner.ingredients.create') }}">Tambah Bahan</x-link-button>
    </x-slot:actions>

    {{-- Definitions only (unit, minimum, price, where it is used). Stock on hand and its changes live on Stok. --}}
    <p class="mb-4 max-w-prose text-sm text-stone-600">
        Data dasar setiap bahan. Untuk melihat stok saat ini, restock, atau stock opname, buka halaman
        <a href="{{ route('owner.stock.index') }}" class="font-medium text-amber-700 hover:underline">Stok</a>.
    </p>

    <ul class="space-y-3 sm:hidden">
        @forelse ($ingredients as $ingredient)
            @php($unit = $ingredient->unit->label())
            <li>
                <a href="{{ route('owner.ingredients.edit', $ingredient) }}" class="flex items-center gap-3 rounded-2xl border border-stone-200 bg-white p-4 active:bg-stone-50">
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">
                            {{ $ingredient->name }}
                            @unless ($ingredient->is_active)
                                <span class="ml-1 rounded-full bg-stone-200 px-2 py-0.5 text-xs font-medium text-stone-600">Nonaktif</span>
                            @endunless
                        </p>
                        <p class="text-sm tabular-nums text-stone-600">Minimum {{ qty($ingredient->min_stock) }} {{ $unit }}, {{ rupiah($ingredient->cost_per_unit) }} / {{ $unit }}</p>
                        <p class="text-xs text-stone-600">{{ $ingredient->products_count ? "Dipakai di {$ingredient->products_count} menu" : 'Belum dipakai di resep' }}</p>
                    </div>
                    <svg class="h-5 w-5 shrink-0 text-stone-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                </a>
            </li>
        @empty
            <li class="rounded-2xl border border-stone-200 bg-white px-4 py-10 text-center text-sm text-stone-600">Belum ada bahan.</li>
        @endforelse
    </ul>

    <x-card class="hidden overflow-x-auto sm:block">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3">Satuan</th>
                    <th class="px-4 py-3 text-right">Stok Minimum</th>
                    <th class="px-4 py-3 text-right">Harga Beli Rata-rata</th>
                    <th class="px-4 py-3">Dipakai Di</th>
                    <th class="px-4 py-3 text-right"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($ingredients as $ingredient)
                    @php($unit = $ingredient->unit->label())
                    <tr>
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('owner.ingredients.edit', $ingredient) }}" class="hover:text-amber-700 hover:underline">{{ $ingredient->name }}</a>
                            @unless ($ingredient->is_active)
                                <span class="ml-1 rounded-full bg-stone-200 px-2 py-0.5 text-xs font-medium text-stone-600">Nonaktif</span>
                            @endunless
                        </td>
                        <td class="px-4 py-3 text-stone-600">{{ $unit }}</td>
                        <td class="px-4 py-3 text-right tabular-nums text-stone-600">{{ qty($ingredient->min_stock) }} {{ $unit }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($ingredient->cost_per_unit) }} / {{ $unit }}</td>
                        <td class="px-4 py-3 text-stone-600">{{ $ingredient->products_count ? "{$ingredient->products_count} menu" : 'Belum dipakai' }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('owner.ingredients.edit', $ingredient) }}" class="font-medium text-amber-700 hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-stone-600">Belum ada bahan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $ingredients->links() }}</div>
</x-owner-layout>
