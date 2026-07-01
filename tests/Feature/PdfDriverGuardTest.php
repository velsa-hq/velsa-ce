<?php

use App\Support\PdfDriverGuard;

it('throws in a deployed environment configured with a non-gotenberg driver', function () {
    expect(fn () => PdfDriverGuard::enforce('production', 'browsershot'))
        ->toThrow(RuntimeException::class, 'not available in a deployed Velsa image');
});

it('throws in a deployed environment with no driver configured', function () {
    expect(fn () => PdfDriverGuard::enforce('staging', null))
        ->toThrow(RuntimeException::class);
});

it('passes in a deployed environment using gotenberg', function () {
    expect(fn () => PdfDriverGuard::enforce('production', 'gotenberg'))
        ->not->toThrow(RuntimeException::class);
});

it('does not constrain the local or testing environments', function () {
    expect(fn () => PdfDriverGuard::enforce('local', 'browsershot'))->not->toThrow(RuntimeException::class)
        ->and(fn () => PdfDriverGuard::enforce('testing', 'dompdf'))->not->toThrow(RuntimeException::class);
});

it('defaults the configured PDF driver to gotenberg when the env var is unset', function () {
    // Re-evaluate the config file with LARAVEL_PDF_DRIVER unset so we assert the
    // file's *default* (the deploy-safety fix), independent of the ambient .env.
    $original = getenv('LARAVEL_PDF_DRIVER');
    putenv('LARAVEL_PDF_DRIVER');
    unset($_ENV['LARAVEL_PDF_DRIVER'], $_SERVER['LARAVEL_PDF_DRIVER']);

    try {
        $config = require base_path('config/laravel-pdf.php');
        expect($config['driver'])->toBe('gotenberg');
    } finally {
        if ($original !== false) {
            putenv("LARAVEL_PDF_DRIVER={$original}");
            $_ENV['LARAVEL_PDF_DRIVER'] = $original;
            $_SERVER['LARAVEL_PDF_DRIVER'] = $original;
        }
    }
});
