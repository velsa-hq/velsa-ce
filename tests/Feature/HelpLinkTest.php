<?php

use App\Services\Handbook;
use Illuminate\Support\Facades\File;

// scans every <HelpLink slug="..."> usage and asserts the slug resolves;
// a typo or renamed handbook file would otherwise be a silent dead link
test('every HelpLink slug resolves to a real handbook page', function () {
    $handbook = app(Handbook::class);

    $files = collect(File::allFiles(resource_path('js/pages')))
        ->filter(fn ($f) => $f->getExtension() === 'tsx');

    $usages = collect();

    foreach ($files as $file) {
        $contents = File::get($file->getRealPath());

        if (! str_contains($contents, 'HelpLink')) {
            continue;
        }

        preg_match_all('/<HelpLink\s+slug="([^"]+)"/', $contents, $matches);

        foreach ($matches[1] as $slug) {
            $usages->push(['slug' => $slug, 'file' => $file->getRelativePathname()]);
        }
    }

    expect($usages)->not->toBeEmpty('Expected at least one <HelpLink> usage.');

    $dead = $usages->filter(fn ($u) => $handbook->find($u['slug']) === null);

    expect($dead->all())->toBe(
        [],
        'Dead handbook links: '.$dead->map(fn ($u) => "{$u['slug']} ({$u['file']})")->implode(', '),
    );
});

test('HelpLink component points at the docs route in a new tab', function () {
    $src = File::get(resource_path('js/components/help-link.tsx'));

    expect($src)->toContain("from '@/routes/docs'")
        ->and($src)->toContain('show(slug).url')
        ->and($src)->toContain('target="_blank"');
});
