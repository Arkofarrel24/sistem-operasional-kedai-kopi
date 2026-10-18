<?php

namespace App\Enums;

enum StockStatus: string
{
    case Ok = 'ok';
    case Low = 'low';
    case Out = 'out';

    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Aman',
            self::Low => 'Menipis',
            self::Out => 'Habis',
        };
    }
}
