<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    /**
     * Cashiers may only open (and reprint) receipts of their own sales.
     */
    public function view(User $user, Sale $sale): bool
    {
        return $sale->user_id === $user->id;
    }
}
