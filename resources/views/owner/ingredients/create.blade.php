<x-owner-layout title="Tambah Bahan">
    <x-card class="max-w-2xl p-6">
        <form novalidate method="POST" action="{{ route('owner.ingredients.store') }}" class="space-y-6">
            @csrf

            <div>
                <x-input-label for="name" value="Nama bahan" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="unit" value="Satuan dasar" />
                <x-select-input id="unit" name="unit" class="mt-1 block w-full sm:w-48" required>
                    <option value="">Pilih satuan</option>
                    @foreach ($units as $unit)
                        <option value="{{ $unit->value }}" @selected(old('unit') === $unit->value)>{{ $unit->label() }}</option>
                    @endforeach
                </x-select-input>
                <p class="mt-1 text-xs text-stone-500">Satuan tidak bisa diubah setelah bahan disimpan.</p>
                <x-input-error :messages="$errors->get('unit')" class="mt-2" />
            </div>

            <div class="grid gap-6 sm:grid-cols-3">
                <div>
                    <x-input-label for="initial_stock" value="Stok awal" />
                    <x-text-input id="initial_stock" name="initial_stock" type="number" min="0" step="0.01" inputmode="decimal"
                                  class="mt-1 block w-full" :value="old('initial_stock', 0)" required />
                    <x-input-error :messages="$errors->get('initial_stock')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="min_stock" value="Stok minimum" />
                    <x-text-input id="min_stock" name="min_stock" type="number" min="0" step="0.01" inputmode="decimal"
                                  class="mt-1 block w-full" :value="old('min_stock', 0)" required />
                    <x-input-error :messages="$errors->get('min_stock')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="cost_per_unit" value="Harga beli / satuan (Rp)" />
                    <x-text-input id="cost_per_unit" name="cost_per_unit" type="number" min="0" step="0.01" inputmode="decimal"
                                  class="mt-1 block w-full" :value="old('cost_per_unit')" required />
                    <x-input-error :messages="$errors->get('cost_per_unit')" class="mt-2" />
                </div>
            </div>

            <label class="flex items-center gap-2">
                <input type="checkbox" name="is_active" value="1" class="rounded border-stone-300 text-amber-600 focus:ring-amber-500"
                       @checked(old('is_active', true))>
                <span class="text-sm">Aktif (bahan nonaktif membuat menu yang memakainya tidak tampil di kasir)</span>
            </label>
            <x-input-error :messages="$errors->get('is_active')" class="mt-2" />

            <div class="flex items-center gap-3">
                <x-primary-button>Simpan</x-primary-button>
                <a href="{{ route('owner.ingredients.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </form>
    </x-card>
</x-owner-layout>
