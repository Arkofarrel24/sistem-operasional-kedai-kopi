<x-owner-layout title="Catat Waste">
    <x-card class="mx-auto max-w-2xl p-6">
        @include('owner.stock._summary')

        <form novalidate method="POST" action="{{ route('owner.stock.waste.store', $ingredient) }}" class="mt-6 space-y-6">
            @csrf
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

            <div class="flex items-center justify-center gap-3">
                <x-primary-button>Simpan waste</x-primary-button>
                <a href="{{ route('owner.stock.index') }}" class="text-sm text-stone-600 hover:underline">Batal</a>
            </div>
        </form>
    </x-card>
</x-owner-layout>
