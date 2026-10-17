<x-owner-layout title="Ubah Menu">
    <div class="grid max-w-5xl gap-6 xl:grid-cols-2">
        <x-card class="p-6">
            <h2 class="mb-4 text-base font-semibold">Data menu</h2>
            <form novalidate method="POST" action="{{ route('owner.products.update', $product) }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                @include('owner.products._form')

                <div class="flex items-center gap-3">
                    <x-primary-button>Simpan</x-primary-button>
                    <a href="{{ route('owner.products.index') }}" class="text-sm text-stone-600 hover:underline">Kembali</a>
                </div>
            </form>
        </x-card>

        <x-card id="resep" class="p-6">
            <h2 class="text-base font-semibold">Resep per 1 porsi</h2>
            <p class="mt-1 text-sm text-stone-600">Jumlah dalam satuan dasar bahan. Stok dikurangi sesuai resep setiap kali menu terjual.</p>

            @if ($product->recipes->isEmpty())
                <p class="mt-4 rounded-lg bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    Belum ada resep. Menu tanpa resep tidak akan tampil di kasir.
                </p>
            @else
                <ul class="mt-4 divide-y divide-stone-100 border-y border-stone-100">
                    @foreach ($product->recipes as $recipe)
                        @php($rowErrors = $errors->getBag('recipe-'.$recipe->id))
                        <li class="flex flex-wrap items-center gap-3 py-3">
                            <span class="min-w-0 flex-1 font-medium">{{ $recipe->ingredient->name }}</span>
                            <form novalidate method="POST" action="{{ route('owner.products.recipes.update', [$product, $recipe]) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <label for="qty-{{ $recipe->id }}" class="sr-only">Jumlah {{ $recipe->ingredient->name }}</label>
                                <x-text-input id="qty-{{ $recipe->id }}" name="quantity" type="number" min="0.01" step="0.01" inputmode="decimal"
                                              class="w-24 text-right text-sm" required
                                              value="{{ $rowErrors->any() ? old('quantity') : (float) $recipe->quantity }}" />
                                <span class="w-10 text-sm text-stone-500">{{ $recipe->ingredient->unit->label() }}</span>
                                <x-secondary-button type="submit" class="!px-3">Simpan</x-secondary-button>
                            </form>
                            <form method="POST" action="{{ route('owner.products.recipes.destroy', [$product, $recipe]) }}"
                                  onsubmit="return confirm(@js('Hapus '.$recipe->ingredient->name.' dari resep?'))">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-sm font-medium text-red-600 hover:underline">Hapus</button>
                            </form>
                            <x-input-error :messages="$rowErrors->get('quantity')" class="w-full text-right" />
                        </li>
                    @endforeach
                </ul>
            @endif

            @if ($availableIngredients->isNotEmpty())
                <form novalidate method="POST" action="{{ route('owner.products.recipes.store', $product) }}" class="mt-6 flex flex-wrap items-start gap-3">
                    @csrf
                    <div class="min-w-[12rem] flex-1">
                        <x-input-label for="ingredient_id" value="Tambah bahan" />
                        <x-select-input id="ingredient_id" name="ingredient_id" class="mt-1 block w-full text-sm" required>
                            <option value="">Pilih bahan</option>
                            @foreach ($availableIngredients as $ingredient)
                                <option value="{{ $ingredient->id }}" @selected($errors->recipe->any() && old('ingredient_id') == $ingredient->id)>
                                    {{ $ingredient->name }} ({{ $ingredient->unit->label() }})
                                </option>
                            @endforeach
                        </x-select-input>
                        <x-input-error :messages="$errors->recipe->get('ingredient_id')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="quantity" value="Jumlah" />
                        <x-text-input id="quantity" name="quantity" type="number" min="0.01" step="0.01" inputmode="decimal"
                                      class="mt-1 w-28 text-right text-sm" :value="$errors->recipe->any() ? old('quantity') : ''" required />
                        <x-input-error :messages="$errors->recipe->get('quantity')" class="mt-2 w-28" />
                    </div>
                    <x-primary-button class="mt-6">Tambah</x-primary-button>
                </form>
            @elseif ($product->recipes->isEmpty())
                <p class="mt-4 text-sm text-stone-600">
                    Belum ada bahan. <a href="{{ route('owner.ingredients.create') }}" class="font-medium text-amber-700 hover:underline">Tambah bahan</a> terlebih dulu.
                </p>
            @endif
        </x-card>
    </div>
</x-owner-layout>
