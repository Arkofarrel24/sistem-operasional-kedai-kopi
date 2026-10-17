<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\User;
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
     * @param  string  $quantity  positive = stock in, negative = stock out (base unit)
     */
    private function record(
        Ingredient $ingredient,
        StockMovementType $type,
        string $quantity,
        User $user,
        ?string $unitCost = null,
        ?string $note = null,
    ): StockMovement {
        return DB::transaction(function () use ($ingredient, $type, $quantity, $user, $unitCost, $note) {
            $locked = Ingredient::whereKey($ingredient->getKey())->lockForUpdate()->firstOrFail();

            $movement = $locked->stockMovements()->create([
                'user_id' => $user->getKey(),
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
