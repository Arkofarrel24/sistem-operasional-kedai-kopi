<x-owner-layout title="Catat Bahan Terbuang">
    <x-card class="max-w-2xl p-6">
        @include('owner.stock._summary')

        <x-confirm-form :action="route('owner.stock.waste.store', $ingredient)" class="mt-6 space-y-6"
                        :title="'Catat '.$ingredient->name.' terbuang?'"
                        message="Stok bahan berkurang sebesar jumlah yang Anda isi dan tercatat di riwayat stok. Catatan ini tidak bisa dihapus; kesalahan hanya bisa dikoreksi lewat penyesuaian stok."
                        confirm="Catat terbuang">
            <div>
                <x-input-label for="quantity" value="Jumlah terbuang ({{ $ingredient->unit->label() }})" />
                <x-text-input id="quantity" name="quantity" type="number" min="0.01" step="0.01" inputmode="decimal"
                              class="mt-1 block w-full sm:w-64" :value="old('quantity')" required autofocus />
                <x-input-error :messages="$errors->get('quantity')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="reason" value="Alasan" />
                <x-text-input id="reason" name="reason" type="text" class="mt-1 block w-full" :value="old('reason')" required placeholder="mis. Susu basi, tumpah saat membuat pesanan" />
                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
            </div>

            <div class="flex items-center gap-3">
                <x-danger-button>Simpan</x-danger-button>
                <a href="{{ route('owner.stock.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </x-confirm-form>
    </x-card>
</x-owner-layout>
