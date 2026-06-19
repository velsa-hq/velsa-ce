<?php

use App\Services\PdfRenderer;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.pdf_renderer.url', 'http://renderer.test');
    config()->set('services.pdf_renderer.timeout', 5);
    config()->set('services.pdf_renderer.retries', 0);
});

it('posts HTML to gotenberg as a multipart attachment and returns the PDF bytes', function () {
    Http::fake([
        'renderer.test/*' => Http::response('%PDF-1.7 fake bytes', 200, [
            'Content-Type' => 'application/pdf',
        ]),
    ]);

    $bytes = app(PdfRenderer::class)
        ->html('<h1>hello</h1>')
        ->bytes();

    expect($bytes)->toBe('%PDF-1.7 fake bytes');

    Http::assertSent(function (Request $request) {
        return $request->url() === 'http://renderer.test/forms/chromium/convert/html'
            && $request->method() === 'POST'
            && $request->isMultipart()
            && collect($request->data())->contains(
                fn ($part) => ($part['name'] ?? null) === 'files'
                    && ($part['filename'] ?? null) === 'index.html'
                    && str_contains($part['contents'] ?? '', '<h1>hello</h1>'),
            );
    });
});

it('forwards paper size, margins, and orientation as form fields', function () {
    Http::fake(['renderer.test/*' => Http::response('PDF', 200)]);

    app(PdfRenderer::class)
        ->html('<p>x</p>')
        ->paperSize(8.5, 11)
        ->margins(0.5, 0.5, 0.5, 0.5)
        ->landscape()
        ->printBackground()
        ->bytes();

    Http::assertSent(function (Request $request) {
        $fields = collect($request->data())
            ->filter(fn ($part) => ($part['filename'] ?? null) === null)
            ->mapWithKeys(fn ($part) => [$part['name'] => $part['contents']]);

        return $fields['paperWidth'] === '8.5in'
            && $fields['paperHeight'] === '11in'
            && $fields['marginTop'] === '0.5in'
            && $fields['marginRight'] === '0.5in'
            && $fields['marginBottom'] === '0.5in'
            && $fields['marginLeft'] === '0.5in'
            && $fields['landscape'] === 'true'
            && $fields['printBackground'] === 'true';
    });
});

it('returns a downloadable Response with attachment disposition', function () {
    Http::fake(['renderer.test/*' => Http::response('PDF', 200)]);

    $response = app(PdfRenderer::class)
        ->html('<p>x</p>')
        ->download('invoice-42.pdf');

    expect($response->headers->get('Content-Type'))->toBe('application/pdf')
        ->and($response->headers->get('Content-Disposition'))->toBe('attachment; filename="invoice-42.pdf"')
        ->and($response->getContent())->toBe('PDF');
});

it('returns a streamable Response with inline disposition', function () {
    Http::fake(['renderer.test/*' => Http::response('PDF', 200)]);

    $response = app(PdfRenderer::class)
        ->html('<p>x</p>')
        ->stream('report.pdf');

    expect($response->headers->get('Content-Disposition'))->toBe('inline; filename="report.pdf"');
});

it('sanitizes quotes and newlines from filenames in the Content-Disposition header', function () {
    Http::fake(['renderer.test/*' => Http::response('PDF', 200)]);

    $response = app(PdfRenderer::class)
        ->html('<p>x</p>')
        ->download("evil\"\n.pdf");

    expect($response->headers->get('Content-Disposition'))->toBe('attachment; filename="evil.pdf"');
});

it('throws a RuntimeException when the renderer returns a non-2xx status', function () {
    Http::fake([
        'renderer.test/*' => Http::response('engine error: chromium crashed', 500),
    ]);

    expect(fn () => app(PdfRenderer::class)->html('<p>x</p>')->bytes())
        ->toThrow(RuntimeException::class, 'PDF renderer returned 500');
});

it('throws a RuntimeException when the URL is not configured', function () {
    config()->set('services.pdf_renderer.url', null);

    expect(fn () => app(PdfRenderer::class)->html('<p>x</p>')->bytes())
        ->toThrow(RuntimeException::class, 'PDF_RENDERER_URL');
});

it('passes arbitrary form fields through withFormField', function () {
    Http::fake(['renderer.test/*' => Http::response('PDF', 200)]);

    app(PdfRenderer::class)
        ->html('<p>x</p>')
        ->withFormField('preferCssPageSize', 'true')
        ->withFormField('emulatedMediaType', 'screen')
        ->bytes();

    Http::assertSent(function (Request $request) {
        $fields = collect($request->data())
            ->filter(fn ($part) => ($part['filename'] ?? null) === null)
            ->mapWithKeys(fn ($part) => [$part['name'] => $part['contents']]);

        return $fields['preferCssPageSize'] === 'true'
            && $fields['emulatedMediaType'] === 'screen';
    });
});
