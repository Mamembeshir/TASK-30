<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidStateTransitionException extends RuntimeException
{
    public function __construct(
        string $entity,
        string $from,
        string $to,
        int $code = 422,
        ?\Throwable $previous = null
    ) {
        parent::__construct(
            "Cannot transition {$entity} from [{$from}] to [{$to}].",
            $code,
            $previous
        );
    }
}
