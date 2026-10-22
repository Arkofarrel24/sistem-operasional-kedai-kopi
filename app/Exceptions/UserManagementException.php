<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A user-management change was rejected (e.g. it would leave no active owner); the message is shown as-is.
 */
class UserManagementException extends RuntimeException {}
