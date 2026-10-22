@if (! $product->is_active)
    <span class="mt-0.5 inline-block rounded bg-stone-200 px-1.5 py-0.5 text-xs font-medium text-stone-700 sm:hidden">Nonaktif</span>
@endif
@if ($product->recipes_count === 0)
    <span class="mt-0.5 inline-block rounded bg-amber-100 px-1.5 py-0.5 text-xs font-medium text-amber-800">Belum ada resep</span>
@endif
@if ($product->inactive_ingredients_count > 0)
    <span class="mt-0.5 inline-block rounded bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700">Bahan nonaktif</span>
@endif
