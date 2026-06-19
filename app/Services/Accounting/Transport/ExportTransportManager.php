<?php

namespace App\Services\Accounting\Transport;

use Illuminate\Contracts\Container\Container;

/**
 * Resolves the configured export transport. Returns null for the
 * "none" setting (manual download / hand-off - no automated send).
 */
class ExportTransportManager
{
    public function __construct(protected Container $app) {}

    public function resolve(): ?ExportTransport
    {
        return match (config('accounting.export.transport')) {
            'email' => $this->app->make(EmailExportTransport::class),
            default => null,
        };
    }
}
