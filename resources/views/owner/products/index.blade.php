<x-owner-layout title="Menu">
    <x-slot:actions>
        <x-link-button href="{{ route('owner.products.create') }}">Tambah Menu</x-link-button>
    </x-slot:actions>

    @include('owner.partials.menu-tabs')

    <form novalidate method="GET" action="{{ route('owner.products.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
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

    @if ($withoutRecipe > 0)
        <p class="mb-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-900">
            {{ $withoutRecipe }} menu aktif belum punya resep, jadi belum tampil di kasir. Buka menu yang bertanda "Belum ada resep" untuk melengkapinya.
        </p>
    @endif

    {{-- Thumbnails only when at least one menu has a photo; empty squares on every row are noise. --}}
    @php($withPhotos = $products->contains(fn ($product) => $product->image_url))

    {{-- Phones: one card per menu, the whole card opens it. --}}
    <ul class="space-y-3 sm:hidden">
        @forelse ($products as $product)
            <li>
                <a href="{{ route('owner.products.edit', $product) }}" class="flex items-center gap-3 rounded-2xl border border-stone-200 bg-white p-4 active:bg-stone-50">
                    @if ($withPhotos)
                        @if ($product->image_url)
                            <img src="{{ $product->image_url }}" alt="" class="h-12 w-12 shrink-0 rounded-lg object-cover">
                        @else
                            <div class="h-12 w-12 shrink-0 rounded-lg bg-stone-100"></div>
                        @endif
                    @endif
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-medium">{{ $product->name }}</p>
                        <p class="text-sm text-stone-600">{{ $product->category->name }}, <span class="tabular-nums">{{ rupiah($product->price) }}</span></p>
                        @include('owner.products._badges')
                    </div>
                    <svg class="h-5 w-5 shrink-0 text-stone-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg>
                </a>
            </li>
        @empty
            <li class="rounded-2xl border border-stone-200 bg-white px-4 py-10 text-center text-sm text-stone-600">Tidak ada menu yang cocok.</li>
        @endforelse
    </ul>

    <x-card class="hidden overflow-x-auto sm:block">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Menu</th>
                    <th class="px-4 py-3">Kategori</th>
                    <th class="px-4 py-3 text-right">Harga</th>
                    <th class="px-4 py-3">Status</th>
                    <th class="px-4 py-3 text-right"><span class="sr-only">Aksi</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($products as $product)
                    <tr>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                @if ($withPhotos)
                                    @if ($product->image_url)
                                        <img src="{{ $product->image_url }}" alt="" class="h-10 w-10 shrink-0 rounded-lg object-cover">
                                    @else
                                        <div class="h-10 w-10 shrink-0 rounded-lg bg-stone-100"></div>
                                    @endif
                                @endif
                                <div>
                                    <p class="font-medium"><a href="{{ route('owner.products.edit', $product) }}" class="hover:text-amber-700 hover:underline">{{ $product->name }}</a></p>
                                    @include('owner.products._badges')
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
                        <td colspan="5" class="px-4 py-10 text-center text-stone-600">Tidak ada menu yang cocok.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $products->links() }}</div>
</x-owner-layout>
