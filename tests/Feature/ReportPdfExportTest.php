<?php

use App\Reports\ReportRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

beforeEach(function () {
    Pdf::fake();
});

it('renders the report PDF view for a registered report slug', function () {
    $user = grantSuperAdmin();
    $slug = collect(app(ReportRegistry::class)->grouped())
        ->flatten(1)->first()->slug();

    $response = $this->actingAs($user)->get("/reports/{$slug}/export.pdf");

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) use ($slug) {
        expect($pdf->viewName)->toBe('pdf.report')
            ->and($pdf->viewData['handler']->slug())->toBe($slug)
            ->and($pdf->viewData['appName'])->toBe(config('app.name'))
            ->and($pdf->viewData['result'])->not->toBeNull();

        return true;
    });
});

it('404s on an unknown report slug', function () {
    $user = grantSuperAdmin();

    $this->actingAs($user)
        ->get('/reports/not-a-real-report/export.pdf')
        ->assertNotFound();
});

it('requires authentication on the PDF export endpoint', function () {
    $slug = collect(app(ReportRegistry::class)->grouped())
        ->flatten(1)->first()->slug();

    $this->get("/reports/{$slug}/export.pdf")
        ->assertRedirect(route('login'));
});

it('passes query-string params through to the handler', function () {
    $user = grantSuperAdmin();
    $registry = app(ReportRegistry::class);
    // prefer a handler with params; else fall back to the first
    $handler = collect($registry->grouped())->flatten(1)
        ->first(fn ($h) => count($h->parameters()) > 0)
        ?? collect($registry->grouped())->flatten(1)->first();
    $slug = $handler->slug();

    $params = collect($handler->parameters())->mapWithKeys(
        fn ($p) => [$p['key'] => $p['default'] ?? 'x'],
    )->all();

    $this->actingAs($user)
        ->get("/reports/{$slug}/export.pdf?".http_build_query($params))
        ->assertOk();

    Pdf::assertRespondedWithPdf(fn ($pdf) => $pdf->viewName === 'pdf.report');
});

it('does not 500 on a malformed date param', function () {
    $user = grantSuperAdmin();

    // garbage must not reach Carbon::parse unguarded
    $this->actingAs($user)
        ->get('/reports/sales-pipeline/export.pdf?from=notadate&to=alsobad')
        ->assertOk();
});
