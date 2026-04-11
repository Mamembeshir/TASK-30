<?php

namespace App\Exceptions;

use RuntimeException;

class IdempotencyConflictException extends RuntimeException
{
    public function __construct(
        public readonly string $cachedResponse,
        int $code = 200,
        ?\Throwable $previous = null
    ) {
        parent::__construct('Idempotency key already used — returning cached response.', $code, $previous);
    }
}
