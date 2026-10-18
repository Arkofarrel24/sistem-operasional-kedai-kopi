@php($unit = $ingredient->unit->label())
<dl class="grid gap-4 rounded-lg bg-stone-50 p-4 text-center text-sm sm:grid-cols-3">
    <div>
        <dt class="text-stone-500">Bahan</dt>
        <dd class="font-medium">{{ $ingredient->name }}</dd>
    </div>
    <div>
        <dt class="text-stone-500">Stok saat ini</dt>
        <dd class="font-medium tabular-nums">{{ qty($ingredient->current_stock) }} {{ $unit }}</dd>
    </div>
    <div>
        <dt class="text-stone-500">Harga beli rata-rata</dt>
        <dd class="font-medium tabular-nums">{{ rupiah($ingredient->cost_per_unit) }} / {{ $unit }}</dd>
    </div>
</dl>
