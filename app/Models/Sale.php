<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Created only through SaleService::checkout().
 */
#[Fillable(['invoice_no', 'user_id', 'total', 'payment_method', 'paid_amount', 'change_amount', 'status', 'void_reason', 'voided_at'])]
class Sale extends Model
{
    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'payment_method' => PaymentMethod::class,
            'paid_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'status' => SaleStatus::class,
            'voided_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function isVoid(): bool
    {
        return $this->status === SaleStatus::Void;
    }
}
