<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A sale operation (checkout, void) was rejected for a business reason; the message is shown as-is.
 */
class SaleException extends RuntimeException {}
