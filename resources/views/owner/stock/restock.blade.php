<x-owner-layout title="Restock">
    <x-card class="max-w-2xl p-6">
        @include('owner.stock._summary')

        @php($unit = $ingredient->unit->label())
        <form novalidate method="POST" action="{{ route('owner.stock.restock.store', $ingredient) }}" class="mt-6 space-y-6"
              x-data="{ quantity: @js(old('quantity', '')), total: @js(old('total_cost', '')) }">
            @csrf
            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <x-input-label for="quantity" value="Jumlah masuk ({{ $unit }})" />
                    <x-text-input id="quantity" name="quantity" type="number" min="0.01" step="0.01" inputmode="decimal"
                                  class="mt-1 block w-full" x-model="quantity" required autofocus />
                    <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="total_cost" value="Total harga nota (Rp)" />
                    <x-text-input id="total_cost" name="total_cost" type="number" min="0" step="1" inputmode="numeric"
                                  class="mt-1 block w-full" x-model="total" required aria-describedby="unit-cost-hint" />
                    <p id="unit-cost-hint" class="mt-1 text-xs text-stone-600" aria-live="polite">
                        <span x-show="quantity > 0 && total !== ''" x-cloak>
                            Setara <span class="font-medium tabular-nums text-stone-900"
                                         x-text="'Rp' + (Math.round(total / quantity * 100) / 100).toLocaleString('id-ID')"></span> / {{ $unit }}
                        </span>
                        <span x-show="!(quantity > 0 && total !== '')">Isi sesuai nota; harga per {{ $unit }} dihitung otomatis.</span>
                    </p>
                    <x-input-error :messages="$errors->get('total_cost')" class="mt-2" />
                </div>
            </div>
            <p class="text-xs text-stone-600">Harga beli rata-rata dihitung ulang: (stok lama × harga lama + jumlah masuk × harga baru) ÷ total stok.</p>

            <div>
                <x-input-label for="note" value="Catatan supplier (opsional)" />
                <x-text-input id="note" name="note" type="text" class="mt-1 block w-full" :value="old('note')" placeholder="mis. Toko Kopi Sumber Rejeki, nota 0412" />
                <x-input-error :messages="$errors->get('note')" class="mt-2" />
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>Simpan restock</x-primary-button>
                <a href="{{ route('owner.stock.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </form>
    </x-card>
</x-owner-layout>
