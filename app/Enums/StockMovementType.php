<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Restock = 'restock';
    case Sale = 'sale';
    case VoidReturn = 'void_return';
    case Adjustment = 'adjustment';
    case Waste = 'waste';

    public function label(): string
    {
        return match ($this) {
            self::Restock => 'Restock',
            self::Sale => 'Penjualan',
            self::VoidReturn => 'Pengembalian void',
            self::Adjustment => 'Penyesuaian',
            self::Waste => 'Terbuang',
        };
    }
}
