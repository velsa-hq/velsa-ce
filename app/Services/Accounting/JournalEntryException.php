<?php

namespace App\Services\Accounting;

use RuntimeException;

/**
 * A manual journal-entry precondition failure (closed fiscal year, an
 * unbalanced/invalid line set, or a model-level posting guard). Framework
 * neutral on purpose: it carries the form `field` the failure maps to so the
 * HTTP layer can surface a keyed validation error, without the service
 * depending on Laravel's ValidationException.
 */
class JournalEntryException extends RuntimeException
{
    public function __construct(public readonly string $field, string $message)
    {
        parent::__construct($message);
    }
}
