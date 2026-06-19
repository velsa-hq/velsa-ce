<?php

namespace App\Services\Reports;

use App\Reports\ReportHandler;
use App\Reports\ReportResult;
use Carbon\CarbonImmutable;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Renders a ReportResult into a styled single-sheet .xlsx: header block,
 * applied params, summary chips, and the table with a frozen header row.
 * Same column order and labels as the CSV / PDF exports.
 */
class ReportXlsxExporter
{
    /**
     * @param  array<int, array{label:string,value:string}>  $paramRows
     */
    public function streamToString(
        ReportHandler $handler,
        ReportResult $result,
        array $paramRows,
        string $appName,
    ): string {
        $spreadsheet = $this->build($handler, $result, $paramRows, $appName);
        $writer = new Xlsx($spreadsheet);

        $temp = tempnam(sys_get_temp_dir(), 'xlsx_');
        $writer->save($temp);
        $body = (string) file_get_contents($temp);
        @unlink($temp);

        return $body;
    }

    /**
     * @param  array<int, array{label:string,value:string}>  $paramRows
     */
    public function build(
        ReportHandler $handler,
        ReportResult $result,
        array $paramRows,
        string $appName,
    ): Spreadsheet {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($handler->title(), 0, 31)); // Excel caps sheet titles at 31 chars

        $columnCount = max(count($result->columns), 2);
        $lastColLetter = Coordinate::stringFromColumnIndex($columnCount);

        $row = 1;

        // app-name strip
        $sheet->setCellValue("A{$row}", $appName);
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->getStyle("A{$row}")
            ->getFont()->setBold(true)->setSize(10)
            ->getColor()->setRGB('6B7280');
        $row++;

        // title
        $sheet->setCellValue("A{$row}", $result->title);
        $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
        $sheet->getRowDimension($row)->setRowHeight(24);
        $row++;

        // description
        if ($result->description !== '') {
            $sheet->setCellValue("A{$row}", $result->description);
            $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
            $sheet->getStyle("A{$row}")->getFont()->setSize(10)
                ->getColor()->setRGB('6B7280');
            $row++;
        }

        $row++; // blank

        // generated-at + applied params
        $sheet->setCellValue("A{$row}", 'Generated');
        $sheet->setCellValue("B{$row}", $result->generatedAt
            ? CarbonImmutable::parse($result->generatedAt)->format('M j, Y g:i A')
            : now()->format('M j, Y g:i A'));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $row++;

        foreach ($paramRows as $param) {
            $sheet->setCellValue("A{$row}", $param['label']);
            $sheet->setCellValue("B{$row}", $param['value']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $row++;
        }

        $row++; // blank

        // summary chips
        if (! empty($result->summary)) {
            $sheet->setCellValue("A{$row}", 'Summary');
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
            $row++;
            foreach ($result->summary as $stat) {
                $sheet->setCellValue("A{$row}", $stat['label']);
                $sheet->setCellValue("B{$row}", $stat['value']);
                $row++;
            }
            $row++; // blank
        }

        // header row - dark fill, white bold text
        $headerRow = $row;
        $col = 1;
        foreach ($result->columns as $column) {
            $letter = Coordinate::stringFromColumnIndex($col);
            $sheet->setCellValue("{$letter}{$headerRow}", $column['label']);
            if (($column['align'] ?? null) === 'right') {
                $sheet->getStyle("{$letter}{$headerRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
            $col++;
        }
        $headerLastLetter = Coordinate::stringFromColumnIndex(max($col - 1, 1));
        $sheet->getStyle("A{$headerRow}:{$headerLastLetter}{$headerRow}")
            ->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle("A{$headerRow}:{$headerLastLetter}{$headerRow}")
            ->getFill()->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1F2937');
        $sheet->getRowDimension($headerRow)->setRowHeight(20);
        $sheet->freezePane('A'.($headerRow + 1));

        $row = $headerRow + 1;

        foreach ($result->rows as $dataRow) {
            $col = 1;
            foreach ($result->columns as $column) {
                $letter = Coordinate::stringFromColumnIndex($col);
                $value = $dataRow[$column['key']] ?? '';
                $sheet->setCellValue("{$letter}{$row}", $value);
                if (($column['align'] ?? null) === 'right') {
                    $sheet->getStyle("{$letter}{$row}")->getAlignment()
                        ->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                }
                $col++;
            }
            $row++;
        }

        for ($i = 1; $i <= $columnCount; $i++) {
            $letter = Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($letter)->setAutoSize(true);
        }

        return $spreadsheet;
    }
}
