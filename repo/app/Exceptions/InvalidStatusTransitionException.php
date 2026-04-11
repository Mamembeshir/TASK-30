<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidStatusTransitionException extends RuntimeException
{
    public function __construct(string $from, string $to, string $entity = 'Entity')
    {
        parent::__construct(
            "Cannot transition {$entity} status from {$from} to {$to}.",
            422,
        );
    }
}
