<x-pos-layout>
    <div class="h-full overflow-y-auto px-4 py-6">
        <div class="mx-auto max-w-sm">
            @include('pos.partials.receipt', ['sale' => $sale])

            <div class="mt-4 grid grid-cols-2 gap-3 print:hidden">
                <a href="{{ route('pos.history') }}"
                   class="flex h-14 items-center justify-center rounded-xl bg-white text-base font-semibold text-stone-800 ring-1 ring-inset ring-stone-300 active:bg-stone-100">Kembali</a>
                <button type="button" onclick="window.print()"
                        class="h-14 rounded-xl bg-stone-900 text-base font-semibold text-white active:bg-stone-700">Cetak struk</button>
            </div>
        </div>
    </div>
</x-pos-layout>
