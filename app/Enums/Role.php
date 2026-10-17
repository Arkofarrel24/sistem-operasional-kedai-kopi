<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Cashier = 'cashier';

    /**
     * Label shown in the UI.
     */
    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Pemilik',
            self::Cashier => 'Kasir',
        };
    }

    /**
     * Named route a user with this role lands on after login.
     */
    public function homeRoute(): string
    {
        return match ($this) {
            self::Owner => 'owner.dashboard',
            self::Cashier => 'pos.index',
        };
    }
}
