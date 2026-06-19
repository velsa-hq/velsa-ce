<?php

namespace App\Services\Reports;

use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use App\Support\Csv;
use App\Support\DateFormatter;
use Carbon\CarbonImmutable;
use Spatie\LaravelPdf\Facades\Pdf;

/**
 * Renders a report result to an in-memory csv / xlsx / pdf string. Shared
 * by export endpoints and the scheduled-report dispatcher so both match.
 */
class ReportRenderer
{
    public function __construct(private readonly ReportXlsxExporter $xlsx) {}

    /**
     * @param  array<string, mixed>  $params
     * @return array{filename: string, mime: string, body: string}
     */
    public function render(ReportHandler $handler, ReportResult $result, array $params, string $format): array
    {
        $stamp = now()->format('Y-m-d');
        $base = "{$handler->slug()}-{$stamp}";

        return match ($format) {
            'csv' => [
                'filename' => "{$base}.csv",
                'mime' => 'text/csv',
                'body' => $this->csv($result),
            ],
            'xlsx' => [
                'filename' => "{$base}.xlsx",
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'body' => $this->xlsx->streamToString($handler, $result, $this->paramRows($handler, $params), (string) config('app.name')),
            ],
            default => [
                'filename' => "{$base}.pdf",
                'mime' => 'application/pdf',
                'body' => $this->pdf($handler, $result, $params),
            ],
        };
    }

    private function csv(ReportResult $result): string
    {
        $out = fopen('php://temp', 'r+');
        fputcsv($out, array_map(fn ($c) => Csv::cell($c['label']), $result->columns));

        foreach ($result->rows as $row) {
            $line = [];
            foreach ($result->columns as $col) {
                $line[] = Csv::cell($row[$col['key']] ?? '');
            }
            fputcsv($out, $line);
        }

        rewind($out);
        $body = (string) stream_get_contents($out);
        fclose($out);

        return $body;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function pdf(ReportHandler $handler, ReportResult $result, array $params): string
    {
        return Pdf::view('pdf.report', [
            'handler' => $handler,
            'result' => $result,
            'paramRows' => $this->paramRows($handler, $params),
            'appName' => (string) config('app.name'),
            'generatedAt' => $result->generatedAt
                ? DateFormatter::reportStamp(CarbonImmutable::parse($result->generatedAt))
                : DateFormatter::reportStamp(now()),
        ])->landscape()->generatePdfContent();
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<int, array{label: string, value: string}>
     */
    private function paramRows(ReportHandler $handler, array $params): array
    {
        $rows = [];
        foreach ($handler->parameters() as $param) {
            $value = $params[$param['key']] ?? null;
            if ($value === null || $value === '') {
                continue;
            }
            $rows[] = ['label' => $param['label'], 'value' => (string) $value];
        }

        return $rows;
    }
}
