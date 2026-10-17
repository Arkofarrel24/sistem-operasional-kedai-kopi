<?php

namespace App\Enums;

/**
 * Base unit an ingredient's stock and recipe quantities are stored in.
 */
enum Unit: string
{
    case Gram = 'gram';
    case Ml = 'ml';
    case Pcs = 'pcs';

    public function label(): string
    {
        return $this->value;
    }
}
