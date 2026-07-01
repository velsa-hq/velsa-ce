<?php

namespace App\Support;

use RuntimeException;

class PdfDriverGuard
{
    /**
     * Driver compatible with the deployed Velsa runtime image. Only Gotenberg
     * ships there (it runs as an internal sidecar service); browsershot needs
     * Node + Chromium, dompdf/weasyprint need packages/binaries - none of which
     * are in the runtime image.
     */
    public const REQUIRED_DRIVER = 'gotenberg';

    /**
     * Environments where a non-Gotenberg driver is allowed (a developer may run
     * browsershot/dompdf locally, and the test suite fakes the PDF facade).
     *
     * @var list<string>
     */
    public const EXEMPT_ENVIRONMENTS = ['local', 'testing'];

    /**
     * Fail loud at boot when a deployed environment is configured with a PDF
     * driver the image can't run, instead of letting every PDF route 500 at
     * request time. This is the regression guard for the "CI-green yet
     * deploy-broken" class: the PDF feature tests fake the facade, so the real
     * driver is never exercised in CI.
     *
     * @throws RuntimeException
     */
    public static function enforce(string $environment, ?string $driver): void
    {
        if (in_array($environment, self::EXEMPT_ENVIRONMENTS, true)) {
            return;
        }

        if ($driver !== self::REQUIRED_DRIVER) {
            throw new RuntimeException(
                "PDF driver [{$driver}] is not available in a deployed Velsa image; "
                .'only ['.self::REQUIRED_DRIVER.'] is bundled. Set LARAVEL_PDF_DRIVER='
                .self::REQUIRED_DRIVER." (and GOTENBERG_URL) on the [{$environment}] environment."
            );
        }
    }
}
