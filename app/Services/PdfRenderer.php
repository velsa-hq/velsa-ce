<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;

/**
 * HTTP client for the Gotenberg PDF sidecar. Posts HTML to
 * /forms/chromium/convert/html so the app image need not bundle Chromium.
 * Config under config/services.pdf_renderer.* (url, timeout, retries).
 */
class PdfRenderer
{
    public function __construct(protected HttpFactory $http) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function view(string $view, array $data = []): PdfBuilder
    {
        return new PdfBuilder($this, view($view, $data)->render());
    }

    public function html(string $html): PdfBuilder
    {
        return new PdfBuilder($this, $html);
    }

    /**
     * @param  array<string, scalar>  $formFields  Gotenberg form fields (paperWidth, margins, etc.)
     */
    public function send(string $html, array $formFields = []): string
    {
        $url = $this->endpoint();
        $timeout = (int) config('services.pdf_renderer.timeout', 30);
        $retries = (int) config('services.pdf_renderer.retries', 0);

        $request = $this->http->timeout($timeout);
        if ($retries > 0) {
            $request = $request->retry($retries, 200, throw: false);
        }

        $response = $request
            ->attach('files', $html, 'index.html', ['Content-Type' => 'text/html'])
            ->post($url, $formFields);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'PDF renderer returned %d from %s: %s',
                $response->status(),
                $url,
                $this->truncate($response->body(), 500),
            ));
        }

        return $response->body();
    }

    protected function endpoint(): string
    {
        $base = config('services.pdf_renderer.url');
        if (! is_string($base) || $base === '') {
            throw new RuntimeException(
                'services.pdf_renderer.url is not configured; set PDF_RENDERER_URL.'
            );
        }

        return rtrim($base, '/').'/forms/chromium/convert/html';
    }

    protected function truncate(string $body, int $length): string
    {
        return strlen($body) > $length
            ? substr($body, 0, $length).'...'
            : $body;
    }
}
