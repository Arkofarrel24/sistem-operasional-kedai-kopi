<x-owner-layout title="Penyesuaian Stok">
    <x-card class="max-w-2xl p-6">
        @include('owner.stock._summary')

        <form novalidate method="POST" action="{{ route('owner.stock.adjustment.store', $ingredient) }}" class="mt-6 space-y-6">
            @csrf
            <div>
                <x-input-label for="counted_stock" value="Stok hasil hitung fisik ({{ $ingredient->unit->label() }})" />
                <x-text-input id="counted_stock" name="counted_stock" type="number" min="0" step="0.01" inputmode="decimal"
                              class="mt-1 block w-full sm:w-64" :value="old('counted_stock')" required autofocus />
                <p class="mt-1 text-xs text-stone-500">Sistem mencatat selisih antara hasil hitung dan stok saat ini.</p>
                <x-input-error :messages="$errors->get('counted_stock')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="reason" value="Alasan" />
                <x-text-input id="reason" name="reason" type="text" class="mt-1 block w-full" :value="old('reason')" required placeholder="mis. Stock opname mingguan" />
                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
            </div>

            <div class="flex items-center gap-3">
                <x-primary-button>Simpan penyesuaian</x-primary-button>
                <a href="{{ route('owner.stock.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </form>
    </x-card>
</x-owner-layout>
