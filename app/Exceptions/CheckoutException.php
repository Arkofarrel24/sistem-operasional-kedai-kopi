<?php

namespace App\Exceptions;

/**
 * A checkout was rejected for a business reason; the message is shown to the cashier as-is.
 */
class CheckoutException extends SaleException {}
