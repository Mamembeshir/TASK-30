<?php

namespace App\Exceptions;

use RuntimeException;

class StaleRecordException extends RuntimeException
{
    public function __construct(
        string $entity = 'record',
        int $code = 409,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            "The {$entity} has been modified by another process. Please reload and try again.",
            $code,
            $previous
        );
    }
}
