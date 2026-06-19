<?php

namespace App\Services\Import;

use Illuminate\Database\Eloquent\Model;

/**
 * Outcome of running one row through an importer (dry-run or commit).
 *
 * A commit row may create more than one record (e.g. a client and its primary
 * contact); all are tracked so reversal removes every one.
 */
class ImportRowResult
{
    /**
     * @param  list<Model>  $created  records persisted for this row (commit only)
     * @param  list<array{field: ?string, message: string}>  $errors
     */
    private function __construct(
        public readonly bool $ok,
        public readonly array $created = [],
        public readonly array $errors = [],
    ) {}

    /**
     * @param  list<Model>  $created
     */
    public static function success(array $created = []): self
    {
        return new self(true, $created, []);
    }

    public static function failure(string $message, ?string $field = null): self
    {
        return new self(false, [], [['field' => $field, 'message' => $message]]);
    }

    /**
     * @param  list<array{field: ?string, message: string}>  $errors
     */
    public static function failures(array $errors): self
    {
        return new self(false, [], $errors);
    }
}
