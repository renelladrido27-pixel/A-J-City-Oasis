<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when Xendit rejects or fails a request. Callers should catch this
 * and show the user a friendly message instead of a raw 500 error.
 */
class PaymentGatewayException extends RuntimeException {}
