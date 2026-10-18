<?php

namespace App\Models;

use App\Enums\StockStatus;
use App\Enums\Unit;
use Database\Factories\IngredientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * current_stock is intentionally not fillable: it may only change through StockService.
 */
#[Fillable(['name', 'unit', 'min_stock', 'cost_per_unit', 'is_active'])]
class Ingredient extends Model
{
    /** @use HasFactory<IngredientFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'unit' => Unit::class,
            'current_stock' => 'decimal:2',
            'min_stock' => 'decimal:2',
            'cost_per_unit' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'recipes')
            ->withPivot('quantity')
            ->withTimestamps();
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isBelowMinimum(): bool
    {
        return bccomp($this->current_stock, $this->min_stock, 2) < 0;
    }

    /**
     * Out when nothing is left, low when below the minimum, otherwise ok.
     */
    public function stockStatus(): StockStatus
    {
        return match (true) {
            bccomp($this->current_stock, '0', 2) <= 0 => StockStatus::Out,
            $this->isBelowMinimum() => StockStatus::Low,
            default => StockStatus::Ok,
        };
    }

    /**
     * Stock value at the current purchase price.
     */
    public function stockValue(): string
    {
        return bcmul($this->current_stock, $this->cost_per_unit, 2);
    }
}
