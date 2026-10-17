<x-owner-layout title="Bahan">
    <x-slot:actions>
        <x-link-button href="{{ route('owner.ingredients.create') }}">Tambah Bahan</x-link-button>
    </x-slot:actions>

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3 text-right">Stok Saat Ini</th>
                    <th class="px-4 py-3 text-right">Stok Minimum</th>
                    <th class="px-4 py-3 text-right">Harga Beli / Satuan</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($ingredients as $ingredient)
                    @php($low = $ingredient->isBelowMinimum())
                    <tr>
                        <td class="px-4 py-3 font-medium">
                            {{ $ingredient->name }}
                            @unless ($ingredient->is_active)
                                <span class="ml-1 rounded-full bg-stone-200 px-2 py-0.5 text-xs font-medium text-stone-600">Nonaktif</span>
                            @endunless
                        </td>
                        <td @class(['px-4 py-3 text-right tabular-nums', 'font-semibold text-red-600' => $low])>
                            {{ qty($ingredient->current_stock) }} {{ $ingredient->unit->label() }}
                            @if ($low)
                                <span class="sr-only">(di bawah stok minimum)</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right tabular-nums text-stone-600">{{ qty($ingredient->min_stock) }} {{ $ingredient->unit->label() }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($ingredient->cost_per_unit) }} / {{ $ingredient->unit->label() }}</td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('owner.ingredients.edit', $ingredient) }}" class="font-medium text-amber-700 hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-stone-500">Belum ada bahan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $ingredients->links() }}</div>
</x-owner-layout>
