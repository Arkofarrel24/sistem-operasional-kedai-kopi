<x-owner-layout title="Restock">
    <x-card class="mx-auto max-w-2xl p-6">
        @include('owner.stock._summary')

        <form novalidate method="POST" action="{{ route('owner.stock.restock.store', $ingredient) }}" class="mt-6 space-y-6">
            @csrf
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <x-input-label for="quantity" value="Jumlah masuk ({{ $ingredient->unit->label() }})" />
                    <x-text-input id="quantity" name="quantity" type="number" min="0.01" step="0.01" inputmode="decimal"
                                  class="mt-1 block w-full" :value="old('quantity')" required autofocus />
                    <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="unit_cost" value="Harga beli / {{ $ingredient->unit->label() }} (Rp)" />
                    <x-text-input id="unit_cost" name="unit_cost" type="number" min="0" step="0.01" inputmode="decimal"
                                  class="mt-1 block w-full" :value="old('unit_cost')" required />
                    <x-input-error :messages="$errors->get('unit_cost')" class="mt-2" />
                </div>
            </div>
            <p class="text-xs text-stone-500">Harga beli rata-rata dihitung ulang: (stok lama × harga lama + jumlah masuk × harga baru) ÷ total stok.</p>

            <div>
                <x-input-label for="note" value="Catatan supplier (opsional)" />
                <x-text-input id="note" name="note" type="text" class="mt-1 block w-full" :value="old('note')" placeholder="mis. Toko Kopi Sumber Rejeki, nota 0412" />
                <x-input-error :messages="$errors->get('note')" class="mt-2" />
            </div>

            <div class="flex items-center justify-center gap-3">
                <x-primary-button>Simpan restock</x-primary-button>
                <a href="{{ route('owner.stock.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </form>
    </x-card>
</x-owner-layout>
