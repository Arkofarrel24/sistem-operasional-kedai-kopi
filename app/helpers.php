<?php

if (! function_exists('rupiah')) {
    /**
     * Format an amount as Rupiah, e.g. Rp15.000 (cents shown only when present: Rp22,50).
     * With $whole the amount is rounded to whole rupiah for display (stored values stay exact).
     */
    function rupiah(string|int|float|null $amount, bool $whole = false): string
    {
        $amount = $whole ? round((float) $amount) : (float) $amount;
        $decimals = fmod($amount, 1.0) == 0.0 ? 0 : 2;

        return 'Rp'.number_format($amount, $decimals, ',', '.');
    }
}

if (! function_exists('qty')) {
    /**
     * Format a stock/recipe quantity in Indonesian notation without trailing zeros, e.g. 1.500 or 0,5.
     */
    function qty(string|int|float|null $quantity): string
    {
        $formatted = number_format((float) $quantity, 2, ',', '.');

        return rtrim(rtrim($formatted, '0'), ',');
    }
}
