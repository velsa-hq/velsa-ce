<?php

use App\Reports\ReportRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;

uses(RefreshDatabase::class);

function firstReportSlug(): string
{
    return collect(app(ReportRegistry::class)->grouped())
        ->flatten(1)->first()->slug();
}

it('returns an XLSX stream for a registered report slug', function () {
    $user = grantSuperAdmin();
    $slug = firstReportSlug();

    $response = $this->actingAs($user)->get("/reports/{$slug}/export.xlsx");

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('embeds the report title and column labels in the workbook', function () {
    $user = grantSuperAdmin();
    $slug = firstReportSlug();

    $response = $this->actingAs($user)->get("/reports/{$slug}/export.xlsx");

    $temp = tempnam(sys_get_temp_dir(), 'test_xlsx_');
    file_put_contents($temp, $response->streamedContent());
    $spreadsheet = IOFactory::load($temp);
    @unlink($temp);

    $sheet = $spreadsheet->getActiveSheet();
    $handler = app(ReportRegistry::class)->get($slug);
    $result = $handler->run([]);

    // row 1 is the app-name strip, title sits on row 2
    expect($sheet->getCell('A2')->getValue())->toBe($result->title);

    // scan for the first column-label match
    $highest = $sheet->getHighestRow();
    $found = false;
    for ($r = 1; $r <= $highest; $r++) {
        if ($sheet->getCell("A{$r}")->getValue() === $result->columns[0]['label']) {
            $found = true;
            break;
        }
    }
    expect($found)->toBeTrue();
});

it('404s on an unknown report slug', function () {
    $user = grantSuperAdmin();

    $this->actingAs($user)
        ->get('/reports/not-a-real-report/export.xlsx')
        ->assertNotFound();
});

it('requires authentication on the XLSX export endpoint', function () {
    $slug = firstReportSlug();

    $this->get("/reports/{$slug}/export.xlsx")
        ->assertRedirect(route('login'));
});
