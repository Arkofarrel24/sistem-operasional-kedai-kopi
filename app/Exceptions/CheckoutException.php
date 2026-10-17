<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A checkout was rejected for a business reason; the message is shown to the cashier as-is.
 */
class CheckoutException extends RuntimeException {}
