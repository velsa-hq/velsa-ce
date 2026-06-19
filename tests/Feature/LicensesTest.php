<?php

use App\Services\LicenseRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('serves /licenses to unauthenticated visitors', function () {
    $this->get('/licenses')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('licenses')
            ->where('app_name', config('app.name'))
            ->has('php', fn ($php) => $php->etc())
            ->has('js', fn ($js) => $js->etc())
        );
});

it('lists every runtime composer package on the page', function () {
    $registry = app(LicenseRegistry::class);
    $php = $registry->php();

    expect($php)->not->toBeEmpty();

    $php->each(function (array $row) {
        expect($row)->toHaveKeys(['name', 'version', 'license', 'homepage']);
        expect($row['name'])->toBeString()->not->toBeEmpty();
        expect($row['version'])->toBeString()->not->toBeEmpty();
        expect($row['license'])->toBeString()->not->toBeEmpty();
    });
});

it('lists only non-dev npm packages', function () {
    $registry = app(LicenseRegistry::class);
    $js = $registry->js();

    expect($js)->not->toBeEmpty();

    // eslint/prettier are dev-deps and should be filtered out
    $names = $js->pluck('name')->all();
    expect($names)->not->toContain('eslint');
    expect($names)->not->toContain('prettier');
});

it('flags no exclusively-copyleft packages in the runtime tree', function () {
    // guard against a dep adding GPL/AGPL/LGPL as its only license option;
    // multi-licensed packages with a permissive alternative are safe
    $registry = app(LicenseRegistry::class);

    $isCopyleft = fn (string $alt): bool => (bool) preg_match(
        '/^(A?GPL|LGPL)(-|$)/i',
        trim($alt),
    );

    $isExclusivelyCopyleft = function (string $license) use ($isCopyleft): bool {
        $alternatives = preg_split('/\s+OR\s+/', trim($license, '()'));

        foreach ($alternatives as $alt) {
            if (! $isCopyleft($alt)) {
                return false;  // a permissive alternative exists
            }
        }

        return true;
    };

    foreach ([$registry->php(), $registry->js()] as $packages) {
        foreach ($packages as $pkg) {
            expect($isExclusivelyCopyleft($pkg['license']))->toBeFalse(
                "Runtime package {$pkg['name']} is exclusively copyleft: {$pkg['license']}",
            );
        }
    }
});
