{{-- Thermal-style receipt; .receipt-printable is the only thing that shows when printing (see app.css). --}}
<div class="receipt-printable mx-auto w-full max-w-[19rem] bg-white px-5 py-6 font-mono text-[13px] leading-relaxed text-stone-900 shadow-sm">
    <div class="text-center">
        <p class="text-base font-bold">{{ config('app.name') }}</p>
        <p>{{ config('app.store_address') }}</p>
    </div>

    @if ($sale->isVoid())
        <div class="mt-3 border-2 border-stone-900 px-2 py-1 text-center">
            <p class="font-bold">VOID</p>
            <p>{{ $sale->voided_at->translatedFormat('j M Y H:i') }}</p>
            <p>Alasan: {{ $sale->void_reason }}</p>
        </div>
    @endif

    <dl class="mt-4 border-t border-dashed border-stone-400 pt-3">
        <div class="flex justify-between gap-2"><dt>No.</dt><dd>{{ $sale->invoice_no }}</dd></div>
        <div class="flex justify-between gap-2"><dt>Tanggal</dt><dd>{{ $sale->created_at->translatedFormat('j M Y H:i') }}</dd></div>
        <div class="flex justify-between gap-2"><dt>Kasir</dt><dd class="truncate">{{ $sale->user->name }}</dd></div>
    </dl>

    <ul class="mt-3 space-y-2 border-t border-dashed border-stone-400 pt-3">
        @foreach ($sale->items as $item)
            <li>
                <p>{{ $item->product_name }}</p>
                <div class="flex justify-between gap-2">
                    <span>{{ $item->quantity }} x {{ rupiah($item->unit_price) }}</span>
                    <span>{{ rupiah($item->subtotal) }}</span>
                </div>
                @if ($item->note)
                    <p class="text-stone-600">Catatan: {{ $item->note }}</p>
                @endif
            </li>
        @endforeach
    </ul>

    <dl class="mt-3 space-y-0.5 border-t border-dashed border-stone-400 pt-3">
        <div class="flex justify-between gap-2 text-base font-bold"><dt>Total</dt><dd>{{ rupiah($sale->total) }}</dd></div>
        <div class="flex justify-between gap-2"><dt>{{ $sale->payment_method->label() }}</dt><dd>{{ rupiah($sale->paid_amount) }}</dd></div>
        @if ($sale->payment_method === \App\Enums\PaymentMethod::Cash)
            <div class="flex justify-between gap-2"><dt>Kembalian</dt><dd>{{ rupiah($sale->change_amount) }}</dd></div>
        @endif
    </dl>

    <p class="mt-5 text-center">Terima kasih</p>
</div>
