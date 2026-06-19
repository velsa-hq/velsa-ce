<?php

namespace App\Services\Accounting\Transport;

/**
 * Outcome of an ExportTransport delivery attempt.
 */
final class TransportResult
{
    public function __construct(
        public readonly bool $delivered,
        public readonly string $detail,
        public readonly ?string $error = null,
    ) {}

    public static function delivered(string $detail): self
    {
        return new self(true, $detail);
    }

    public static function failed(string $error): self
    {
        return new self(false, 'Delivery failed', $error);
    }
}
