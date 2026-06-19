<?php

namespace App\Services\Import;

use RuntimeException;

/**
 * Thrown inside a per-row transaction to roll back partial writes; carries the
 * structured failures for recording.
 */
class ImportRowException extends RuntimeException
{
    /**
     * @param  list<array{field: ?string, message: string}>  $failures
     */
    public function __construct(public readonly array $failures)
    {
        parent::__construct('Import row rejected.');
    }
}
