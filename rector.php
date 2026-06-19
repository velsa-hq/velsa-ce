<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

/**
 * Rector - automated PHP refactoring for the hardening/cleanup phase.
 *
 * Run rule-set by rule-set, NOT all at once: dead-code first, then
 * code-quality, then (later, deliberately) the type sets - with the full
 * test suite + PHPStan green and the diff reviewed between each. Start scoped
 * to app/ to bound the blast radius; widen once the early passes are trusted.
 *
 *   vendor/bin/rector --dry-run   # preview
 *   vendor/bin/rector             # apply, then: pint, phpstan, pest
 */
return RectorConfig::configure()
    ->withPaths([
        __DIR__.'/app',
    ])
    // Pass 1 = dead-code only (pure, low-risk removals). Enable codeQuality
    // next (selectively - its instanceof / control-flow flips are churny),
    // then the type sets, each as its own reviewed + tested pass.
    ->withPreparedSets(
        deadCode: true,
    )
    ->withSkip([
        // Generated or framework-shaped files we don't want Rector reshaping.
        __DIR__.'/app/../bootstrap/cache',
    ]);
