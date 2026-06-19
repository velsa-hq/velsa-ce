<?php

use App\Models\ExportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('auto-generates a unique slug when none is provided', function () {
    $a = ExportTemplate::factory()->create(['name' => 'My Template', 'slug' => null]);
    $b = ExportTemplate::factory()->create(['name' => 'My Template', 'slug' => null]);

    expect($a->slug)->toBe('my-template')
        ->and($b->slug)->toBe('my-template-2');
});

it('demotes any previous default when a new template is marked default', function () {
    $first = ExportTemplate::factory()->default()->create(['slug' => 'first']);
    $second = ExportTemplate::factory()->default()->create(['slug' => 'second']);

    expect($first->fresh()->is_default)->toBeFalse()
        ->and($second->fresh()->is_default)->toBeTrue();
});

it('resolveDefault returns the marked default when one exists', function () {
    ExportTemplate::factory()->create(['slug' => 'a', 'is_default' => false]);
    $default = ExportTemplate::factory()->default()->create(['slug' => 'b']);

    expect(ExportTemplate::resolveDefault()?->id)->toBe($default->id);
});

it('resolveDefault falls back to the most-recently-updated template when none is default', function () {
    ExportTemplate::factory()->create(['slug' => 'older']);
    $newest = ExportTemplate::factory()->create(['slug' => 'newer']);

    expect(ExportTemplate::resolveDefault()?->id)->toBe($newest->id);
});
