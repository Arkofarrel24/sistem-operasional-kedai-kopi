<x-owner-layout title="Menu">
    <x-slot:actions>
        <x-link-button href="{{ route('owner.products.create') }}">Tambah Menu</x-link-button>
    </x-slot:actions>

    @include('owner.partials.menu-tabs')

    <form method="GET" action="{{ route('owner.products.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <x-input-label for="category" value="Kategori" />
            <x-select-input id="category" name="category" class="mt-1 text-sm">
                <option value="">Semua kategori</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected(($filters['category'] ?? null) == $category->id)>{{ $category->name }}</option>
                @endforeach
            </x-select-input>
        </div>
        <div>
            <x-input-label for="status" value="Status" />
            <x-select-input id="status" name="status" class="mt-1 text-sm">
                <option value="">Semua status</option>
                <option value="active" @selected(($filters['status'] ?? null) === 'active')>Aktif</option>
                <option value="inactive" @selected(($filters['status'] ?? null) === 'inactive')>Nonaktif</option>
            </x-select-input>
        </div>
        <x-secondary-button type="submit">Terapkan</x-secondary-button>
        @if (array_filter($filters))
            <a href="{{ route('owner.products.index') }}" class="py-2 text-sm text-stone-600 hover:underline">Reset</a>
        @endif
    </form>

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Menu</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3 text-right">Harga</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($products as $product)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($product->image_url)
                                    <img src="{{ $product->image_url }}" alt="" class="h-10 w-10 rounded-lg object-cover">
                                @else
                                    <div class="h-10 w-10 rounded-lg bg-stone-100"></div>
                                @endif
                                <div>
                                    <p class="font-medium">{{ $product->name }}</p>
                                    @if ($product->recipes_count === 0)
                                        <span class="mt-0.5 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">Belum ada resep</span>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-stone-600">{{ $product->category->name }}</td>
                        <td class="px-4 py-3 text-right tabular-nums">{{ rupiah($product->price) }}</td>
                        <td class="px-4 py-3">
                            @if ($product->is_active)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Aktif</span>
                            @else
                                <span class="rounded-full bg-stone-200 px-2 py-0.5 text-xs font-medium text-stone-600">Nonaktif</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('owner.products.edit', $product) }}" class="font-medium text-amber-700 hover:underline">Ubah</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-stone-500">Tidak ada menu yang cocok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $products->links() }}</div>
</x-owner-layout>
