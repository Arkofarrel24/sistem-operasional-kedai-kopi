<?php

namespace App\Exceptions;

/**
 * Thrown when the locked ingredient stock cannot cover the whole cart.
 */
class InsufficientStockException extends CheckoutException
{
    /**
     * @param  list<array{ingredient: string, unit: string, required: string, available: string, missing: string}>  $shortages
     */
    public function __construct(public readonly array $shortages)
    {
        $parts = array_map(
            fn (array $shortage) => "{$shortage['ingredient']} kurang ".qty($shortage['missing'])." {$shortage['unit']}",
            $shortages,
        );

        parent::__construct('Stok tidak cukup: '.implode(', ', $parts).'.');
    }
}
