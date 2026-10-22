<x-owner-layout title="Kategori">
    <x-slot:actions>
        <x-link-button href="{{ route('owner.categories.create') }}">Tambah Kategori</x-link-button>
    </x-slot:actions>

    @include('owner.partials.menu-tabs')

    <x-card class="overflow-x-auto">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Nama</th>
                    <th class="px-4 py-3 text-center">Jumlah Menu</th>
                    <th class="px-4 py-3 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($categories as $category)
                    <tr>
                        <td class="px-4 py-3 font-medium"><a href="{{ route('owner.categories.edit', $category) }}" class="hover:text-amber-700 hover:underline">{{ $category->name }}</a></td>
                        <td class="px-4 py-3 text-center tabular-nums">{{ $category->products_count }}</td>
                        <td class="px-4 py-3">
                            <div class="flex justify-center gap-3">
                                <a href="{{ route('owner.categories.edit', $category) }}" class="font-medium text-amber-700 hover:underline">Ubah</a>
                                @if ($category->products_count === 0)
                                    <x-confirm-form :action="route('owner.categories.destroy', $category)" method="DELETE"
                                                    :title="'Hapus kategori '.$category->name.'?'"
                                                    message="Kategori ini belum dipakai menu mana pun, jadi bisa dihapus. Tindakan ini tidak bisa dibatalkan."
                                                    confirm="Hapus kategori">
                                        <button type="submit" class="font-medium text-red-600 hover:underline">Hapus</button>
                                    </x-confirm-form>
                                @else
                                    <span class="text-stone-400" title="Kategori masih dipakai oleh menu">Hapus</span>
                                @endif
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="px-4 py-10 text-center text-stone-500">Belum ada kategori.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $categories->links() }}</div>
</x-owner-layout>
