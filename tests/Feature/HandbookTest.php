<?php

use App\Models\User;
use App\Services\Handbook;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the handbook index', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/docs')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('docs/index')
            ->has('nav')
            ->where('first_slug', 'getting-started')
        );
});

it('renders a single doc by slug with parsed html and toc', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/docs/getting-started')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('docs/show')
            ->where('doc.slug', 'getting-started')
            ->where('doc.section', 'Getting started')
            ->where('doc.title', 'Welcome')
            ->has('doc.html')
            ->has('doc.toc')
        );
});

it('returns 404 for an unknown slug', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/docs/this-does-not-exist')
        ->assertNotFound();
});

it('rewrites :::video <slug> blocks into hydration placeholders', function () {
    $doc = app(Handbook::class)->find('contracts/drafting-and-sending');

    expect($doc)->not->toBeNull();
    expect($doc['html'])->toContain('data-handbook-video="draft-contract"');
    expect($doc['html'])->toContain('data-title="Draft and send a contract"');
    expect($doc['html'])->toContain('data-duration="149"');
    expect($doc['html'])->toContain('data-youtube-id="5_1fjIwywiY"');
});

it('parses a heading that immediately follows a :::video embed', function () {
    // regression: video directive used to swallow the blank line after it,
    // gluing the <div> to the next heading so CommonMark left it raw
    $doc = app(Handbook::class)->all()->firstWhere('slug', 'reports/ad-hoc-builder');

    expect($doc['html'])->toContain('data-handbook-video="report-builder"');
    expect($doc['html'])->toMatch('/<h2[^>]*>.*Where it lives<\/h2>/s');
    expect($doc['html'])->not->toContain('## Where it lives');
});

it('embeds videos via youtube.com, not youtube-nocookie', function () {
    $component = file_get_contents(resource_path('js/components/handbook-video.tsx'));

    expect($component)->toContain('https://www.youtube.com/embed/');
    expect($component)->not->toContain('youtube-nocookie');
});
