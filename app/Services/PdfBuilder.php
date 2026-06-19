<?php

namespace App\Services;

use Illuminate\Http\Response;

/**
 * Fluent builder for a single PDF render. Chain config methods, then call
 * bytes() / stream() / download(). Paper/margin config is forwarded to
 * Gotenberg as multipart form fields; the unit suffix (in, mm, cm, pt, pc, px)
 * is appended to each numeric value because Gotenberg requires units in the
 * dimension strings.
 */
class PdfBuilder
{
    /** @var array<string, string> */
    protected array $formFields = [];

    public function __construct(
        protected PdfRenderer $renderer,
        protected string $html,
    ) {}

    public function paperSize(float $width, float $height, string $unit = 'in'): self
    {
        $this->formFields['paperWidth'] = $width.$unit;
        $this->formFields['paperHeight'] = $height.$unit;

        return $this;
    }

    public function margins(
        float $top,
        float $right,
        float $bottom,
        float $left,
        string $unit = 'in',
    ): self {
        $this->formFields['marginTop'] = $top.$unit;
        $this->formFields['marginRight'] = $right.$unit;
        $this->formFields['marginBottom'] = $bottom.$unit;
        $this->formFields['marginLeft'] = $left.$unit;

        return $this;
    }

    public function landscape(bool $landscape = true): self
    {
        $this->formFields['landscape'] = $landscape ? 'true' : 'false';

        return $this;
    }

    public function printBackground(bool $print = true): self
    {
        $this->formFields['printBackground'] = $print ? 'true' : 'false';

        return $this;
    }

    /**
     * When true, Gotenberg honors @page CSS rules for paper size.
     */
    public function preferCssPageSize(bool $prefer = true): self
    {
        $this->formFields['preferCssPageSize'] = $prefer ? 'true' : 'false';

        return $this;
    }

    /**
     * Escape hatch for Gotenberg options the builder doesn't expose first-class.
     */
    public function withFormField(string $name, string $value): self
    {
        $this->formFields[$name] = $value;

        return $this;
    }

    public function bytes(): string
    {
        return $this->renderer->send($this->html, $this->formFields);
    }

    /**
     * Response with inline disposition (renders in the tab, no save dialog).
     */
    public function stream(string $filename = 'document.pdf'): Response
    {
        return $this->respond($filename, 'inline');
    }

    /**
     * Response with attachment disposition (forces a download).
     */
    public function download(string $filename = 'document.pdf'): Response
    {
        return $this->respond($filename, 'attachment');
    }

    protected function respond(string $filename, string $disposition): Response
    {
        $bytes = $this->bytes();

        return new Response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf(
                '%s; filename="%s"',
                $disposition,
                $this->sanitizeFilename($filename),
            ),
            'Content-Length' => (string) strlen($bytes),
        ]);
    }

    protected function sanitizeFilename(string $filename): string
    {
        // strip quotes/newlines that would break the Content-Disposition header
        return str_replace(['"', "\r", "\n"], '', $filename);
    }
}
