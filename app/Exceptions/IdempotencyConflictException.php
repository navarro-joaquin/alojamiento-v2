<?php

namespace App\Exceptions;

use Exception;

/**
 * An operation UUID was reused with different data than the original request.
 */
class IdempotencyConflictException extends Exception
{
    public static function paraOperacion(string $operationUuid): self
    {
        return new self("La operación {$operationUuid} ya fue registrada con datos distintos.");
    }
}
