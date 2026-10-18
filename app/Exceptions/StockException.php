<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A stock change (restock, adjustment, waste) was rejected; the message is shown to the owner as-is.
 */
class StockException extends RuntimeException {}
