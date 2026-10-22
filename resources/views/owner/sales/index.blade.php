<x-owner-layout title="Transaksi">
    <form novalidate method="GET" action="{{ route('owner.sales.index') }}" class="mb-4 flex flex-wrap items-end gap-3">
        <div>
            <x-input-label for="from" value="Dari tanggal" />
            <x-text-input id="from" name="from" type="date" class="mt-1 text-sm" :value="$filters['from'] ?? ''" />
        </div>
        <div>
            <x-input-label for="to" value="Sampai tanggal" />
            <x-text-input id="to" name="to" type="date" class="mt-1 text-sm" :value="$filters['to'] ?? ''" />
        </div>
        <div>
            <x-input-label for="cashier" value="Kasir" />
            <x-select-input id="cashier" name="cashier" class="mt-1 text-sm">
                <option value="">Semua kasir</option>
                @foreach ($cashiers as $cashier)
                    <option value="{{ $cashier->id }}" @selected(($filters['cashier'] ?? null) == $cashier->id)>{{ $cashier->name }}{{ $cashier->is_active ? '' : ' (nonaktif)' }}</option>
                @endforeach
            </x-select-input>
        </div>
        <div>
            <x-input-label for="status" value="Status" />
            <x-select-input id="status" name="status" class="mt-1 text-sm">
                <option value="">Semua status</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </x-select-input>
        </div>
        <x-secondary-button type="submit">Terapkan</x-secondary-button>
        @if (array_filter($filters))
            <a href="{{ route('owner.sales.index') }}" class="py-2 text-sm text-stone-600 hover:underline">Reset</a>
        @endif
    </form>
    <x-input-error :messages="$errors->get('to')" class="mb-4" />

    @if ($totals['count'] > 0)
        <p class="mb-3 text-sm text-stone-600" aria-live="polite">
            <span class="font-semibold tabular-nums text-stone-900">{{ number_format($totals['count'], 0, ',', '.') }} transaksi</span>.
            {{ number_format($totals['completed'], 0, ',', '.') }} selesai senilai <span class="font-semibold tabular-nums text-stone-900">{{ rupiah($totals['revenue'], whole: true) }}</span>@if ($totals['void']), {{ number_format($totals['void'], 0, ',', '.') }} void (tidak dihitung)@endif.
        </p>
    @endif

    @php($statusBadge = fn ($sale) => $sale->isVoid() ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-800')

    {{-- Phones: one card per sale, the whole card opens it. --}}
    <ul class="space-y-3 sm:hidden">
        @forelse ($sales as $sale)
            <li>
                <a href="{{ route('owner.sales.show', $sale) }}" class="block rounded-2xl border border-stone-200 bg-white p-4 active:bg-stone-50">
                    <div class="flex items-baseline justify-between gap-3">
                        <p class="font-medium">{{ $sale->invoice_no }}</p>
                        <p @class(['font-semibold tabular-nums', 'text-stone-500 line-through' => $sale->isVoid()])>{{ rupiah($sale->total) }}</p>
                    </div>
                    <div class="mt-1 flex items-center justify-between gap-3 text-sm text-stone-600">
                        <p class="min-w-0 truncate">{{ $sale->created_at->translatedFormat('j M H:i') }}, {{ $sale->user->name }}, {{ $sale->payment_method->label() }}</p>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-xs font-medium {{ $statusBadge($sale) }}">{{ $sale->status->label() }}</span>
                    </div>
                </a>
            </li>
        @empty
            <li class="rounded-2xl border border-stone-200 bg-white px-4 py-10 text-center text-sm text-stone-600">
                {{ array_filter($filters) ? 'Tidak ada transaksi yang cocok dengan filter. Coba ubah atau reset filternya.' : 'Belum ada transaksi. Transaksi yang dicatat kasir akan muncul di sini.' }}
            </li>
        @endforelse
    </ul>

    <x-card class="hidden overflow-x-auto sm:block">
        <table class="min-w-full divide-y divide-stone-200 text-sm">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-wide text-stone-500">
                <tr>
                    <th class="px-4 py-3">Transaksi</th>
                    <th class="px-4 py-3">Kasir</th>
                    <th class="px-4 py-3">Pembayaran</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
                @forelse ($sales as $sale)
                    <tr>
                        <td class="whitespace-nowrap px-4 py-3">
                            <a href="{{ route('owner.sales.show', $sale) }}" class="font-medium text-amber-800 hover:underline">{{ $sale->invoice_no }}</a>
                            <p class="text-xs tabular-nums text-stone-600">{{ $sale->created_at->translatedFormat('j M Y H:i') }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $sale->user->name }}</td>
                        <td class="whitespace-nowrap px-4 py-3 text-stone-600">{{ $sale->payment_method->label() }}, {{ $sale->items_count }} menu</td>
                        <td @class(['whitespace-nowrap px-4 py-3 text-right tabular-nums', 'text-stone-500 line-through' => $sale->isVoid()])>{{ rupiah($sale->total) }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $statusBadge($sale) }}">{{ $sale->status->label() }}</span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-10 text-center text-stone-600">
                            {{ array_filter($filters) ? 'Tidak ada transaksi yang cocok dengan filter. Coba ubah atau reset filternya.' : 'Belum ada transaksi. Transaksi yang dicatat kasir akan muncul di sini.' }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $sales->links() }}</div>
</x-owner-layout>
