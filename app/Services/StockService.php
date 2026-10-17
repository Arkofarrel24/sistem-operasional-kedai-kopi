<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only place allowed to change ingredients.current_stock.
 * Every change is written to stock_movements in the same database transaction.
 */
class StockService
{
    public function recordInitialStock(Ingredient $ingredient, string $quantity, User $user): ?StockMovement
    {
        if (bccomp($quantity, '0', 2) === 0) {
            return null;
        }

        return $this->record($ingredient, StockMovementType::Adjustment, $quantity, $user, note: 'Stok awal');
    }

    /**
     * Lock the given ingredient rows until the surrounding transaction ends.
     * Rows are always locked in id order so concurrent checkouts cannot deadlock each other.
     *
     * @param  array<int>  $ids
     * @return Collection<int, Ingredient> keyed by id
     */
    public function lockIngredients(array $ids): Collection
    {
        if (DB::transactionLevel() === 0) {
            throw new \LogicException('Ingredients can only be locked inside a database transaction.');
        }

        return Ingredient::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    /**
     * Book the ingredient usage of a sale (stock out).
     *
     * @param  string  $quantity  amount used, as a positive number in the ingredient's base unit
     */
    public function recordSaleUsage(Ingredient $ingredient, string $quantity, Sale $sale, User $user): StockMovement
    {
        return $this->record($ingredient, StockMovementType::Sale, bcmul($quantity, '-1', 2), $user, saleId: $sale->id);
    }

    /**
     * @param  string  $quantity  positive = stock in, negative = stock out (base unit)
     */
    private function record(
        Ingredient $ingredient,
        StockMovementType $type,
        string $quantity,
        User $user,
        ?string $unitCost = null,
        ?string $note = null,
        ?int $saleId = null,
    ): StockMovement {
        return DB::transaction(function () use ($ingredient, $type, $quantity, $user, $unitCost, $note, $saleId) {
            $locked = Ingredient::whereKey($ingredient->getKey())->lockForUpdate()->firstOrFail();

            $movement = $locked->stockMovements()->create([
                'user_id' => $user->getKey(),
                'sale_id' => $saleId,
                'type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'note' => $note,
            ]);

            $locked->forceFill(['current_stock' => bcadd($locked->current_stock, $quantity, 2)])->save();

            $ingredient->setRawAttributes($locked->getAttributes(), true);

            return $movement;
        });
    }
}
