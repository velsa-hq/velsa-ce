<?php

namespace App\Services\Import;

use Generator;
use SplFileObject;

/**
 * Minimal CSV reader over SplFileObject (native fgetcsv handles quoting and
 * embedded newlines). Headers are always resolved to names - either the
 * first row, or synthesized "Column 1..N" for headerless files - so the rest
 * of the pipeline maps by column name uniformly.
 */
class CsvReader
{
    public function __construct(
        private readonly string $path,
        private readonly bool $hasHeader = true,
        private readonly string $delimiter = ',',
    ) {}

    /**
     * The column names, in file order.
     *
     * @return list<string>
     */
    public function headers(): array
    {
        $file = $this->open();
        $first = $file->fgetcsv();

        if (! is_array($first) || $first === [null]) {
            return [];
        }

        if ($this->hasHeader) {
            return array_map(
                fn ($v, $i) => $this->cleanHeader((string) ($v ?? ''), $i),
                $first,
                array_keys($first),
            );
        }

        return array_map(fn (int $i) => 'Column '.($i + 1), range(0, count($first) - 1));
    }

    /**
     * Yield each data row as [row_number => array<string, ?string>] keyed by
     * the resolved column names. Row numbers are 1-based over data rows
     * (the header, if any, is row 0 and not yielded).
     *
     * @return Generator<int, array<string, string|null>>
     */
    public function rows(): Generator
    {
        $headers = $this->headers();

        if ($headers === []) {
            return;
        }

        $file = $this->open();

        if ($this->hasHeader) {
            $file->fgetcsv(); // discard header line
        }

        $rowNumber = 0;

        while (! $file->eof()) {
            $cells = $file->fgetcsv();

            if (! is_array($cells) || $cells === [null]) {
                continue; // blank line
            }

            $rowNumber++;
            $assoc = [];

            foreach ($headers as $i => $name) {
                $assoc[$name] = array_key_exists($i, $cells) ? ($cells[$i] ?? null) : null;
            }

            yield $rowNumber => $assoc;
        }
    }

    private function open(): SplFileObject
    {
        $file = new SplFileObject($this->path, 'r');
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::DROP_NEW_LINE);
        $file->setCsvControl($this->delimiter);

        return $file;
    }

    private function cleanHeader(string $value, int $index): string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? 'Column '.($index + 1) : $trimmed;
    }
}
