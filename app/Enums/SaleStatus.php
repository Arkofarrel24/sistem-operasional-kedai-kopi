<?php

namespace App\Enums;

enum SaleStatus: string
{
    case Completed = 'completed';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Selesai',
            self::Void => 'Void',
        };
    }
}
