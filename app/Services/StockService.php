<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Exceptions\StockException;
use App\Models\Ingredient;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only place allowed to change ingredients.current_stock.
 *
 * Every change locks the ingredient row, is written to stock_movements and updates the
 * current_stock cache in the same database transaction. Each movement stores the purchase
 * price per unit at that moment (unit_cost) so cost of goods sold stays historically accurate.
 */
class StockService
{
    public function recordInitialStock(Ingredient $ingredient, string $quantity, User $user): ?StockMovement
    {
        if (bccomp($quantity, '0', 2) === 0) {
            return null;
        }

        return $this->change($ingredient, fn (Ingredient $locked) => $this->apply(
            $locked, StockMovementType::Adjustment, $quantity, $user, $locked->cost_per_unit, 'Stok awal',
        ));
    }

    /**
     * Stock in from a purchase. cost_per_unit becomes the weighted average of the stock on hand
     * and the incoming stock (PLAN.md rule 9):
     *
     *   new cost = (old stock x old cost + quantity x unit cost) / (old stock + quantity)
     *
     * rounded half-up to 2 decimals; with no stock on hand the new cost is simply the unit cost.
     */
    public function restock(Ingredient $ingredient, string $quantity, string $unitCost, User $user, ?string $note = null): StockMovement
    {
        $this->ensurePositive($quantity, 'Jumlah restock harus lebih besar dari 0.');

        if (bccomp($unitCost, '0', 2) < 0) {
            throw new StockException('Harga beli per satuan tidak boleh negatif.');
        }

        return $this->change($ingredient, function (Ingredient $locked) use ($quantity, $unitCost, $user, $note) {
            $locked->cost_per_unit = $this->weightedAverageCost($locked->current_stock, $locked->cost_per_unit, $quantity, $unitCost);

            return $this->apply($locked, StockMovementType::Restock, $quantity, $user, $unitCost, $this->cleanNote($note));
        });
    }

    /**
     * Restock from the supplier's bill: the owner enters the total paid, the unit cost is derived.
     */
    public function restockFromTotal(Ingredient $ingredient, string $quantity, string $totalCost, User $user, ?string $note = null): StockMovement
    {
        $this->ensurePositive($quantity, 'Jumlah restock harus lebih besar dari 0.');

        if (bccomp($totalCost, '0', 2) < 0) {
            throw new StockException('Total harga nota tidak boleh negatif.');
        }

        return $this->restock($ingredient, $quantity, $this->unitCostFromTotal($totalCost, $quantity), $user, $note);
    }

    /**
     * Total paid ÷ quantity, rounded half-up to 2 decimals (both are never negative).
     */
    public function unitCostFromTotal(string $totalCost, string $quantity): string
    {
        return bcadd(bcadd(bcdiv($totalCost, $quantity, 6), '0.005', 6), '0', 2);
    }

    /**
     * Stock opname: book the difference between the physical count and the system stock.
     * Returns null when the count already matches (nothing to record).
     */
    public function adjustToCount(Ingredient $ingredient, string $countedStock, User $user, string $reason): ?StockMovement
    {
        if (bccomp($countedStock, '0', 2) < 0) {
            throw new StockException('Stok hasil hitung tidak boleh negatif.');
        }

        $reason = $this->requireReason($reason);

        return $this->change($ingredient, function (Ingredient $locked) use ($countedStock, $user, $reason) {
            $difference = bcsub($countedStock, $locked->current_stock, 2);

            if (bccomp($difference, '0', 2) === 0) {
                return null;
            }

            return $this->apply($locked, StockMovementType::Adjustment, $difference, $user, $locked->cost_per_unit, $reason);
        });
    }

    /**
     * Spoiled or spilled stock. Cannot exceed what is on hand.
     */
    public function recordWaste(Ingredient $ingredient, string $quantity, User $user, string $reason): StockMovement
    {
        $this->ensurePositive($quantity, 'Jumlah terbuang harus lebih besar dari 0.');
        $reason = $this->requireReason($reason);

        return $this->change($ingredient, function (Ingredient $locked) use ($quantity, $user, $reason) {
            if (bccomp($quantity, $locked->current_stock, 2) > 0) {
                $unit = $locked->unit->label();

                throw new StockException('Jumlah terbuang ('.qty($quantity)." {$unit}) melebihi stok saat ini (".qty($locked->current_stock)." {$unit}).");
            }

            return $this->apply($locked, StockMovementType::Waste, bcmul($quantity, '-1', 2), $user, $locked->cost_per_unit, $reason);
        });
    }

    /**
     * Lock the given ingredient rows until the surrounding transaction ends.
     * Rows are always locked in id order so concurrent checkouts and voids cannot deadlock each other.
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
     * Book the ingredient usage of a sale (stock out) at the current purchase price.
     *
     * @param  string  $quantity  amount used, as a positive number in the ingredient's base unit
     */
    public function recordSaleUsage(Ingredient $ingredient, string $quantity, Sale $sale, User $user): StockMovement
    {
        return $this->change($ingredient, fn (Ingredient $locked) => $this->apply(
            $locked, StockMovementType::Sale, bcmul($quantity, '-1', 2), $user, $locked->cost_per_unit, saleId: $sale->id,
        ));
    }

    /**
     * Put back what a voided sale took, at the purchase price recorded on the original sale movement.
     */
    public function recordVoidReturn(Ingredient $ingredient, string $quantity, Sale $sale, User $user, ?string $unitCost): StockMovement
    {
        $this->ensurePositive($quantity, 'Jumlah pengembalian harus lebih besar dari 0.');

        return $this->change($ingredient, fn (Ingredient $locked) => $this->apply(
            $locked, StockMovementType::VoidReturn, $quantity, $user, $unitCost, 'Void '.$sale->invoice_no, $sale->id,
        ));
    }

    public function weightedAverageCost(string $stock, string $cost, string $incoming, string $incomingCost): string
    {
        if (bccomp($stock, '0', 2) <= 0) {
            return bcadd($incomingCost, '0', 2);
        }

        $value = bcadd(bcmul($stock, $cost, 4), bcmul($incoming, $incomingCost, 4), 4);
        $average = bcdiv($value, bcadd($stock, $incoming, 2), 6);

        return bcadd(bcadd($average, '0.005', 6), '0', 2); // round half-up (values are never negative)
    }

    /**
     * Lock the ingredient row and run the change on it inside a transaction; the caller's model is
     * refreshed with the saved values afterwards.
     *
     * @template T
     *
     * @param  callable(Ingredient): T  $callback
     * @return T
     */
    private function change(Ingredient $ingredient, callable $callback): mixed
    {
        return DB::transaction(function () use ($ingredient, $callback) {
            $locked = Ingredient::whereKey($ingredient->getKey())->lockForUpdate()->firstOrFail();

            $result = $callback($locked);

            $ingredient->setRawAttributes($locked->getAttributes(), true);

            return $result;
        });
    }

    /**
     * @param  string  $quantity  positive = stock in, negative = stock out (base unit)
     */
    private function apply(
        Ingredient $locked,
        StockMovementType $type,
        string $quantity,
        User $user,
        ?string $unitCost = null,
        ?string $note = null,
        ?int $saleId = null,
    ): StockMovement {
        $movement = $locked->stockMovements()->create([
            'user_id' => $user->getKey(),
            'sale_id' => $saleId,
            'type' => $type,
            'quantity' => $quantity,
            'unit_cost' => $unitCost,
            'note' => $note,
        ]);

        $locked->forceFill(['current_stock' => bcadd($locked->current_stock, $quantity, 2)])->save();

        return $movement;
    }

    private function ensurePositive(string $quantity, string $message): void
    {
        if (bccomp($quantity, '0', 2) <= 0) {
            throw new StockException($message);
        }
    }

    private function requireReason(string $reason): string
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new StockException('Alasan wajib diisi.');
        }

        return $reason;
    }

    private function cleanNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : $note;
    }
}
