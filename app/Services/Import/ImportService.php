<?php

namespace App\Services\Import;

use App\Enums\ImportStatus;
use App\Models\ImportError;
use App\Models\ImportJob;
use App\Services\AuditLogger;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Import pipeline: read file, apply column map, preview dry-run, commit, and
 * reversal. One transaction per row so a bad row never poisons the rest.
 * Importers own only per-row validate + persist; state lives here.
 */
class ImportService
{
    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * @return list<string>
     */
    public function headers(ImportJob $job): array
    {
        return $this->reader($job)->headers();
    }

    /**
     * Best-guess map of field key => source column, matching field aliases
     * against headers.
     *
     * @param  list<string>  $headers
     * @return array<string, string|null>
     */
    public function autoMap(Importer $importer, array $headers): array
    {
        $byToken = [];

        foreach ($headers as $header) {
            $token = $this->normalize($header);
            $byToken[$token] ??= $header; // first header wins on collision
        }

        $map = [];

        foreach ($importer->fields() as $field) {
            $map[$field->key] = null;

            foreach ($field->matchTokens() as $token) {
                if (isset($byToken[$token])) {
                    $map[$field->key] = $byToken[$token];
                    break;
                }
            }
        }

        return $map;
    }

    /**
     * Dry-run every row through validation without writing; records errors and
     * moves the job to Previewed.
     *
     * @return array{total: int, valid: int, errors: int}
     */
    public function preview(ImportJob $job): array
    {
        $importer = $this->registry->fresh($job->kind);
        $job->errors()->delete();

        $total = 0;
        $valid = 0;
        $errors = 0;

        foreach ($this->reader($job)->rows() as $rowNumber => $row) {
            $total++;
            $mapped = $this->applyMap($importer, $row, $job->column_map ?? []);
            $result = $importer->import($mapped, dryRun: true);

            if ($result->ok) {
                $valid++;

                continue;
            }

            $errors++;
            $this->recordErrors($job, $rowNumber, $result->errors, $row);
        }

        $summary = ['total' => $total, 'valid' => $valid, 'errors' => $errors];

        $job->update([
            'status' => ImportStatus::Previewed,
            'total_rows' => $total,
            'valid_rows' => $valid,
            'error_rows' => $errors,
            'previewed_at' => now(),
            'summary_json' => ['preview' => $summary],
        ]);

        return $summary;
    }

    /**
     * Import valid rows for real, each in its own transaction; records created
     * rows (for reversal) and per-row failures, then moves the job to Completed.
     *
     * @return array{total: int, created: int, errors: int}
     */
    public function commit(ImportJob $job, bool $readOnlyCovered = false): array
    {
        $importer = $this->registry->fresh($job->kind);
        $job->errors()->delete();
        $job->records()->delete();

        $total = 0;
        $created = 0;
        $errors = 0;

        // suppress per-model audit hooks across the batch; one summary event
        // below instead of an audit row per record (AU-6)
        AuditLogger::withoutAuditing(function () use ($importer, $job, &$total, &$created, &$errors): void {
            foreach ($this->reader($job)->rows() as $rowNumber => $row) {
                $total++;
                $mapped = $this->applyMap($importer, $row, $job->column_map ?? []);

                try {
                    DB::transaction(function () use ($importer, $mapped, $rowNumber, $job): void {
                        $result = $importer->import($mapped, dryRun: false);

                        if (! $result->ok) {
                            throw new ImportRowException($result->errors);
                        }

                        foreach ($result->created as $model) {
                            $job->records()->create([
                                'importable_type' => $model->getMorphClass(),
                                'importable_id' => $model->getKey(),
                                'row_number' => $rowNumber,
                            ]);
                        }
                    });

                    $created++;
                } catch (ImportRowException $e) {
                    $errors++;
                    $this->recordErrors($job, $rowNumber, $e->failures, $row);
                } catch (Throwable $e) {
                    $errors++;
                    $this->recordErrors($job, $rowNumber, [['field' => null, 'message' => $e->getMessage()]], $row);
                }
            }
        });

        $summary = ['total' => $total, 'created' => $created, 'errors' => $errors];

        $this->audit->record(
            eventType: 'import.committed',
            subject: $job,
            payload: ['kind' => $job->kind, 'read_only_covered' => $readOnlyCovered] + $summary,
        );

        $job->update([
            'status' => ImportStatus::Completed,
            'total_rows' => $total,
            'created_rows' => $created,
            'error_rows' => $errors,
            'committed_at' => now(),
            // read-only during commit, so reversal knows nothing changed underneath
            'read_only_covered' => $readOnlyCovered,
            'summary_json' => array_merge($job->summary_json ?? [], ['commit' => $summary]),
        ]);

        return $summary;
    }

    /**
     * Delete only the records this import created. A record now referenced
     * elsewhere is left in place and counted as skipped, never cascaded.
     * Counts are rows, not individual records.
     *
     * @return array{deleted: int, skipped: int}
     */
    public function reverse(ImportJob $job): array
    {
        $importer = $this->registry->getOrFail($job->kind);
        $deleted = 0;
        $skipped = 0;

        // delete a row's records together, dependents first (id-desc); a row
        // referenced outside the import is left wholly intact
        $rows = $job->records()
            ->orderByDesc('row_number')
            ->orderByDesc('id')
            ->get()
            ->groupBy('row_number');

        // suppress per-model audit hooks across the batch; summary event after (AU-6)
        AuditLogger::withoutAuditing(function () use ($importer, $rows, &$deleted, &$skipped): void {
            foreach ($rows as $records) {
                $models = $records->map(fn ($r) => $r->importable)->filter()->values();

                if ($models->contains(fn (Model $m) => $importer->isReferenced($m))) {
                    $skipped++;

                    continue;
                }

                try {
                    DB::transaction(function () use ($records): void {
                        foreach ($records as $record) {
                            $record->importable?->forceDelete();
                        }
                    });

                    $records->each(fn ($record) => $record->delete());
                    $deleted++;
                } catch (Throwable) {
                    $skipped++;
                }
            }
        });

        $this->audit->record(
            eventType: 'import.reversed',
            subject: $job,
            payload: ['kind' => $job->kind, 'deleted' => $deleted, 'skipped' => $skipped],
        );

        $job->update([
            'status' => ImportStatus::Reversed,
            'reversed_at' => now(),
            'summary_json' => array_merge($job->summary_json ?? [], [
                'reversal' => ['deleted' => $deleted, 'skipped' => $skipped],
            ]),
        ]);

        return ['deleted' => $deleted, 'skipped' => $skipped];
    }

    /**
     * Downloadable error report: row number, field, message, raw row as JSON.
     */
    public function errorCsv(ImportJob $job): string
    {
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['row_number', 'field', 'message', 'raw_row']);

        $job->errors()->orderBy('row_number')->orderBy('id')
            ->chunk(500, function ($chunk) use ($handle): void {
                foreach ($chunk as $error) {
                    /** @var ImportError $error */
                    fputcsv($handle, Csv::row([
                        $error->row_number,
                        $error->field,
                        $error->message,
                        json_encode($error->raw_row_json, JSON_UNESCAPED_SLASHES),
                    ]));
                }
            });

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Header-keyed source row -> field-keyed row, including only fields mapped
     * to a present source column.
     *
     * @param  array<string, string|null>  $row
     * @param  array<string, string|null>  $map
     * @return array<string, string|null>
     */
    private function applyMap(Importer $importer, array $row, array $map): array
    {
        $mapped = [];

        foreach ($importer->fields() as $field) {
            $source = $map[$field->key] ?? null;

            if ($source !== null && array_key_exists($source, $row)) {
                $mapped[$field->key] = $row[$source];
            }
        }

        return $mapped;
    }

    /**
     * @param  list<array{field: ?string, message: string}>  $failures
     * @param  array<string, string|null>  $rawRow
     */
    private function recordErrors(ImportJob $job, int $rowNumber, array $failures, array $rawRow): void
    {
        foreach ($failures as $failure) {
            $job->errors()->create([
                'row_number' => $rowNumber,
                'field' => $failure['field'],
                'message' => $failure['message'],
                'raw_row_json' => $rawRow,
            ]);
        }
    }

    private function reader(ImportJob $job): CsvReader
    {
        $path = Storage::disk($job->disk)->path($job->file_path);

        return new CsvReader($path, $job->has_header, $job->delimiter);
    }

    private function normalize(string $value): string
    {
        return Str::of($value)->lower()->replaceMatches('/[^a-z0-9]+/', '')->value();
    }
}
