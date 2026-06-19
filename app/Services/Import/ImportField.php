<?php

namespace App\Services\Import;

use Illuminate\Support\Str;

/**
 * Describes one target field an importer can populate: how it's labelled in
 * the mapping UI, whether a row needs it, and the column-name aliases used
 * to auto-guess which source column feeds it.
 */
class ImportField
{
    /**
     * @param  list<string>  $aliases  extra source-header names that auto-map here
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly bool $required = false,
        public readonly string $hint = '',
        public readonly array $aliases = [],
    ) {}

    /**
     * Normalised tokens this field will accept as an auto-match against a
     * source column header (lowercased, non-alphanumerics stripped).
     *
     * @return list<string>
     */
    public function matchTokens(): array
    {
        $tokens = array_merge([$this->key, $this->label], $this->aliases);

        return array_values(array_unique(array_map(
            fn (string $t) => Str::of($t)->lower()->replaceMatches('/[^a-z0-9]+/', '')->value(),
            $tokens,
        )));
    }
}
