<x-owner-layout :title="'Transaksi '.$sale->invoice_no">
    <x-slot:actions>
        <x-secondary-button onclick="window.print()">Cetak struk</x-secondary-button>
    </x-slot:actions>

    <div class="mx-auto grid max-w-4xl gap-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
        <div>
            @include('pos.partials.receipt', ['sale' => $sale])
        </div>

        <div class="space-y-6">
            @if ($sale->isVoid())
                <x-card class="p-6">
                    <h2 class="text-center text-base font-semibold text-red-700">Transaksi ini sudah di-void</h2>
                    <dl class="mt-3 space-y-2 text-sm">
                        <div><dt class="text-stone-500">Alasan</dt><dd class="font-medium">{{ $sale->void_reason }}</dd></div>
                        <div><dt class="text-stone-500">Waktu void</dt><dd class="font-medium">{{ $sale->voided_at->translatedFormat('j M Y H:i') }}</dd></div>
                        @if ($voidedBy)
                            <div><dt class="text-stone-500">Di-void oleh</dt><dd class="font-medium">{{ $voidedBy->name }}</dd></div>
                        @endif
                    </dl>
                    <p class="mt-3 text-sm text-stone-600">Stok bahan sudah dikembalikan dan transaksi ini tidak dihitung di laporan.</p>
                </x-card>
            @else
                <x-card class="p-6">
                    <h2 class="text-center text-base font-semibold">Void transaksi</h2>
                    <p class="mt-1 text-center text-sm text-stone-600">Transaksi tetap tersimpan dengan status void, stok bahan dikembalikan, dan tidak dihitung di laporan. Tindakan ini tidak bisa dibatalkan.</p>

                    <x-confirm-form :action="route('owner.sales.void', $sale)" class="mt-4 space-y-4"
                                    :title="'Void transaksi '.$sale->invoice_no.'?'"
                                    message="Stok bahan dikembalikan dan transaksi tidak dihitung lagi di laporan. Transaksi tetap tersimpan dengan status void, dan tindakan ini tidak bisa dibatalkan."
                                    confirm="Void transaksi">
                        <div>
                            <x-input-label for="reason" value="Alasan void" />
                            <x-text-input id="reason" name="reason" type="text" class="mt-1 block w-full" :value="old('reason')" required placeholder="mis. Pelanggan batal, salah input menu" />
                            <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                        </div>
                        <div class="flex justify-center"><x-danger-button>Void transaksi</x-danger-button></div>
                    </x-confirm-form>
                </x-card>
            @endif

            <a href="{{ route('owner.sales.index') }}" class="block text-center text-sm text-stone-600 hover:underline">Kembali ke daftar transaksi</a>
        </div>
    </div>
</x-owner-layout>
