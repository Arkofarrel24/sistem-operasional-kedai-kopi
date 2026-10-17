<x-owner-layout title="Ubah Bahan">
    <x-card class="max-w-2xl p-6">
        <form novalidate method="POST" action="{{ route('owner.ingredients.update', $ingredient) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="name" value="Nama bahan" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $ingredient->name)" required autofocus />
                <x-input-error :messages="$errors->get('name')" class="mt-2" />
            </div>

            <dl class="grid gap-4 rounded-lg bg-stone-50 p-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-stone-500">Satuan dasar</dt>
                    <dd class="font-medium">{{ $ingredient->unit->label() }}</dd>
                </div>
                <div>
                    <dt class="text-stone-500">Stok saat ini</dt>
                    <dd class="font-medium tabular-nums">{{ qty($ingredient->current_stock) }} {{ $ingredient->unit->label() }}</dd>
                </div>
                <p class="text-xs text-stone-500 sm:col-span-2">Stok berubah lewat transaksi, restock, atau penyesuaian stok, tidak dari form ini.</p>
            </dl>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <x-input-label for="min_stock" value="Stok minimum ({{ $ingredient->unit->label() }})" />
                    <x-text-input id="min_stock" name="min_stock" type="number" min="0" step="0.01" inputmode="decimal"
                                  class="mt-1 block w-full" :value="old('min_stock', (float) $ingredient->min_stock)" required />
                    <x-input-error :messages="$errors->get('min_stock')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="cost_per_unit" value="Harga beli / {{ $ingredient->unit->label() }} (Rp)" />
                    <x-text-input id="cost_per_unit" name="cost_per_unit" type="number" min="0" step="0.01" inputmode="decimal"
                                  class="mt-1 block w-full" :value="old('cost_per_unit', (float) $ingredient->cost_per_unit)" required />
                    <x-input-error :messages="$errors->get('cost_per_unit')" class="mt-2" />
                </div>
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>Simpan</x-primary-button>
                <a href="{{ route('owner.ingredients.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </form>
    </x-card>
</x-owner-layout>
