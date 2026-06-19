<?php

namespace App\Services\Import;

use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\Eloquent\Model;

/**
 * Shared scaffolding for importers: a default read-only stance and a helper
 * that flattens a failed Laravel validator into the row-result error shape.
 */
abstract class AbstractImporter implements Importer
{
    public function requiresReadOnly(): bool
    {
        return false;
    }

    public function isReferenced(Model $model): bool
    {
        return false;
    }

    /**
     * Flatten a failed validator into the [{field, message}] list a failed
     * ImportRowResult carries.
     *
     * @return list<array{field: ?string, message: string}>
     */
    protected function failuresFrom(ValidatorContract $validator): array
    {
        $failures = [];

        foreach ($validator->errors()->toArray() as $field => $messages) {
            foreach ($messages as $message) {
                $failures[] = ['field' => $field, 'message' => $message];
            }
        }

        return $failures;
    }

    /**
     * Trim a mapped value to a non-empty string, or null. CSVs are all
     * strings; blank cells should read as "absent", not "".
     */
    protected function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
