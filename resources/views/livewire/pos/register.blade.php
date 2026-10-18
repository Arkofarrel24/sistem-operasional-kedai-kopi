<div class="h-full overflow-y-auto md:grid md:grid-cols-[minmax(0,1fr)_22rem] md:overflow-hidden lg:grid-cols-[minmax(0,1fr)_25rem]">
    {{-- Menu --}}
    <section class="flex min-h-0 flex-col md:overflow-hidden" aria-label="Daftar menu">
        <div class="space-y-3 border-b border-stone-200 bg-stone-100 px-4 pb-3 pt-4 sm:px-6">
            <label for="pos-search" class="sr-only">Cari menu</label>
            <div class="relative">
                <svg class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-stone-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <input id="pos-search" type="search" wire:model.live.debounce.250ms="search" placeholder="Cari menu"
                       autocomplete="off"
                       class="h-12 w-full rounded-xl border-stone-300 pl-12 text-base focus:border-amber-500 focus:ring-amber-500">
            </div>

            <div class="-mx-4 flex overflow-x-auto px-4 pb-1 sm:-mx-6 sm:px-6">
            <div class="mx-auto flex gap-2" role="group" aria-label="Kategori">
                @foreach ([null => 'Semua'] + $this->categories->pluck('name', 'id')->all() as $id => $name)
                    @php($selected = $categoryId === ($id ?: null))
                    <button type="button" wire:click="selectCategory({{ $id ?: 'null' }})" aria-pressed="{{ $selected ? 'true' : 'false' }}"
                            @class([
                                'h-11 shrink-0 rounded-full px-5 text-base font-medium transition-colors',
                                'bg-stone-900 text-white' => $selected,
                                'bg-white text-stone-700 ring-1 ring-inset ring-stone-300 active:bg-stone-200' => ! $selected,
                            ])>{{ $name }}</button>
                @endforeach
            </div>
            </div>
        </div>

        <div class="flex-1 px-4 pb-6 pt-4 sm:px-6 md:overflow-y-auto">
            @forelse ($this->menu as $categoryName => $products)
                <div @class(['mt-6' => ! $loop->first])>
                    <h2 class="mb-2 text-center text-sm font-semibold text-stone-500">{{ $categoryName }}</h2>
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($products as $product)
                            @php($inCart = $cart[$product->id]['quantity'] ?? 0)
                            <button type="button" wire:key="product-{{ $product->id }}" wire:click="add({{ $product->id }})"
                                    @class([
                                        'relative flex min-h-[5.5rem] flex-col items-center justify-center rounded-xl bg-white px-8 py-3 text-center ring-1 ring-inset transition active:scale-[0.98] active:bg-amber-50',
                                        'ring-2 ring-amber-500' => $inCart,
                                        'ring-stone-200' => ! $inCart,
                                    ])>
                                <span class="text-base font-semibold leading-snug text-stone-900">{{ $product->name }}</span>
                                <span class="mt-1 text-[0.95rem] tabular-nums text-stone-600">{{ rupiah($product->price) }}</span>
                                @if ($inCart)
                                    <span class="absolute right-2 top-2 flex h-7 min-w-7 items-center justify-center rounded-full bg-amber-500 px-2 text-sm font-bold tabular-nums text-stone-900"
                                          aria-label="{{ $inCart }} di keranjang">{{ $inCart }}</span>
                                @endif
                            </button>
                        @endforeach
                    </div>
                </div>
            @empty
                <div class="py-16 text-center">
                    <p class="text-base text-stone-600">
                        @if (trim($search) !== '')
                            Tidak ada menu dengan nama "{{ $search }}".
                        @else
                            Belum ada menu yang bisa dijual. Owner perlu mengaktifkan menu dan melengkapi resepnya.
                        @endif
                    </p>
                    @if (trim($search) !== '' || $categoryId)
                        <button type="button" wire:click="resetFilters" class="mt-3 text-base font-medium text-amber-700 underline">Tampilkan semua menu</button>
                    @endif
                </div>
            @endforelse
        </div>
    </section>

    {{-- Cart & payment --}}
    <section id="pesanan" class="flex min-h-0 flex-col border-t border-stone-200 bg-white md:border-l md:border-t-0" aria-label="Pesanan">
        <div class="flex h-14 shrink-0 items-center justify-between px-4">
            <h2 class="text-lg font-semibold">
                Pesanan
                @if ($this->itemCount)
                    <span class="font-normal text-stone-500">({{ $this->itemCount }} item)</span>
                @endif
            </h2>
            @if ($cart)
                <button type="button" wire:click="clearCart" wire:confirm="Kosongkan pesanan ini?"
                        class="h-10 rounded-lg px-3 text-sm font-medium text-stone-600 active:bg-stone-100">Kosongkan</button>
            @endif
        </div>

        <ul class="min-h-[6rem] flex-1 divide-y divide-stone-100 overflow-y-auto border-y border-stone-100">
            @forelse ($cart as $productId => $line)
                @php($product = $this->cartProducts->get($productId))
                @continue(! $product)
                <li wire:key="line-{{ $productId }}" class="px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <p class="min-w-0 pt-1 text-base font-medium leading-snug">{{ $product->name }}</p>
                        <p class="shrink-0 pt-1 text-base font-semibold tabular-nums">{{ rupiah(bcmul($product->price, (string) $line['quantity'], 2)) }}</p>
                    </div>
                    <div class="mt-2 flex items-center gap-3">
                        <div class="flex items-center rounded-xl ring-1 ring-inset ring-stone-300">
                            <button type="button" wire:click="decrement({{ $productId }})"
                                    class="flex h-11 w-11 items-center justify-center rounded-l-xl text-stone-700 active:bg-stone-100"
                                    aria-label="Kurangi {{ $product->name }}">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" d="M5 12h14" /></svg>
                            </button>
                            <span class="w-9 text-center text-base font-semibold tabular-nums" aria-live="polite">{{ $line['quantity'] }}</span>
                            <button type="button" wire:click="increment({{ $productId }})"
                                    class="flex h-11 w-11 items-center justify-center rounded-r-xl text-stone-700 active:bg-stone-100"
                                    aria-label="Tambah {{ $product->name }}">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14" /></svg>
                            </button>
                        </div>
                        <label for="note-{{ $productId }}" class="sr-only">Catatan {{ $product->name }}</label>
                        <input id="note-{{ $productId }}" type="text" wire:model.blur="cart.{{ $productId }}.note"
                               maxlength="{{ \App\Services\SaleService::MAX_NOTE_LENGTH }}" placeholder="Catatan (mis. less sugar)"
                               class="h-11 min-w-0 flex-1 rounded-xl border-stone-300 text-sm focus:border-amber-500 focus:ring-amber-500">
                    </div>
                    @error('cart.'.$productId.'.note') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    @error('cart.'.$productId.'.quantity') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </li>
            @empty
                <li class="px-4 py-10 text-center text-base text-stone-500">Ketuk menu untuk menambahkannya ke pesanan.</li>
            @endforelse
        </ul>

        <form wire:submit="checkout" novalidate class="shrink-0 space-y-3 bg-white p-4">
            <div class="flex items-baseline justify-between">
                <span class="text-base text-stone-600">Total</span>
                <span class="text-3xl font-bold tabular-nums tracking-tight">{{ rupiah($this->total) }}</span>
            </div>

            <div class="grid grid-cols-3 gap-2" role="radiogroup" aria-label="Metode pembayaran">
                @foreach (\App\Enums\PaymentMethod::cases() as $method)
                    @php($active = $paymentMethod === $method->value)
                    <button type="button" role="radio" aria-checked="{{ $active ? 'true' : 'false' }}" wire:click="selectPayment('{{ $method->value }}')"
                            @class([
                                'h-12 rounded-xl text-base font-semibold transition-colors',
                                'bg-stone-900 text-white' => $active,
                                'bg-stone-100 text-stone-700 active:bg-stone-200' => ! $active,
                            ])>{{ $method->label() }}</button>
                @endforeach
            </div>

            @if ($paymentMethod === 'cash')
                <div>
                    <label for="paid-amount" class="text-sm font-medium text-stone-600">Uang diterima</label>
                    <div class="relative mt-1">
                        <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-base text-stone-500">Rp</span>
                        <input id="paid-amount" type="number" inputmode="numeric" min="0" step="1" wire:model.live.debounce.300ms="paidAmount"
                               @class([
                                   'h-12 w-full rounded-xl pl-11 text-right text-lg font-semibold tabular-nums focus:ring-amber-500',
                                   'border-red-400 focus:border-red-500' => $errors->has('paidAmount'),
                                   'border-stone-300 focus:border-amber-500' => ! $errors->has('paidAmount'),
                               ])
                               aria-describedby="cash-status" @disabled(! $cart)>
                    </div>
                    @if ($this->quickAmounts)
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($this->quickAmounts as $amount)
                                <button type="button" wire:click="setPaidAmount('{{ $amount }}')"
                                        @class([
                                            'h-10 rounded-lg px-3 text-sm font-medium tabular-nums ring-1 ring-inset',
                                            'bg-amber-50 text-amber-900 ring-amber-400' => $paidAmount === $amount,
                                            'bg-white text-stone-700 ring-stone-300 active:bg-stone-100' => $paidAmount !== $amount,
                                        ])>{{ $loop->first ? 'Uang pas' : rupiah($amount) }}</button>
                            @endforeach
                        </div>
                    @endif

                    <p id="cash-status" class="mt-2 flex items-baseline justify-between text-base" aria-live="polite">
                        @if ($this->cashDifference !== null && $cart && bccomp($this->cashDifference, '0', 2) > 0)
                            <span class="text-red-600">Kurang</span>
                            <span class="font-semibold tabular-nums text-red-600">{{ rupiah($this->cashDifference) }}</span>
                        @elseif ($this->cashDifference !== null && $cart)
                            <span class="text-stone-600">Kembalian</span>
                            <span class="text-xl font-bold tabular-nums text-emerald-700">{{ rupiah(bcmul($this->cashDifference, '-1', 2)) }}</span>
                        @endif
                    </p>
                    @error('paidAmount') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            @error('cart') <p role="alert" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror
            @error('checkout') <p role="alert" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p> @enderror

            <button type="submit" @disabled(! $cart) wire:loading.attr="disabled" wire:target="checkout"
                    class="flex h-16 w-full items-center justify-center rounded-2xl bg-amber-500 text-xl font-bold text-stone-950 transition-colors active:bg-amber-600 disabled:cursor-not-allowed disabled:bg-stone-200 disabled:text-stone-400">
                <span wire:loading.remove wire:target="checkout">{{ $cart ? 'Bayar '.rupiah($this->total) : 'Bayar' }}</span>
                <span wire:loading wire:target="checkout">Memproses…</span>
            </button>
        </form>
    </section>

    {{-- Receipt after a successful checkout --}}
    @if ($this->completedSale)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-stone-900/60 p-4" role="dialog" aria-modal="true" aria-labelledby="receipt-title">
            <div class="flex max-h-full w-full max-w-sm flex-col overflow-hidden rounded-2xl bg-stone-100">
                <div class="flex items-center gap-2 bg-emerald-700 px-5 py-3 text-white">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2.25" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                    <p id="receipt-title" class="text-lg font-semibold">Pembayaran berhasil</p>
                </div>
                <div class="overflow-y-auto p-4">
                    @include('pos.partials.receipt', ['sale' => $this->completedSale])
                </div>
                <div class="grid grid-cols-2 gap-3 border-t border-stone-200 bg-white p-4">
                    <button type="button" onclick="window.print()"
                            class="h-14 rounded-xl bg-white text-base font-semibold text-stone-800 ring-1 ring-inset ring-stone-300 active:bg-stone-100">Cetak struk</button>
                    <button type="button" wire:click="newTransaction" autofocus
                            class="h-14 rounded-xl bg-stone-900 text-base font-semibold text-white active:bg-stone-700">Transaksi baru</button>
                </div>
            </div>
        </div>
    @endif
</div>
