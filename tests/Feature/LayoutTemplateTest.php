<?php

use App\Models\Booking;
use App\Models\Diagram;
use App\Models\LayoutTemplate;
use App\Models\Space;
use App\Models\User;
use Database\Seeders\LayoutTemplatesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin();
    $this->space = Space::factory()->create();
    $this->booking = Booking::factory()->create();
    $this->diagram = Diagram::query()->create([
        'booking_id' => $this->booking->id,
        'space_id' => $this->space->id,
        'name' => 'Test diagram',
        'scale_px_per_foot' => 10,
    ]);
});

it('applying a template clones its objects into a new diagram version', function () {
    $template = LayoutTemplate::query()->create([
        'space_id' => null,
        'name' => 'Banquet 80',
        'category' => 'banquet',
        'objects_json' => [
            ['id' => 'tpl_1', 'type' => 'round_table_60', 'x' => 100, 'y' => 100, 'rotation' => 0, 'props' => ['seats' => 8]],
            ['id' => 'tpl_2', 'type' => 'round_table_60', 'x' => 200, 'y' => 100, 'rotation' => 0, 'props' => ['seats' => 8]],
        ],
        'object_count' => 2,
        'seat_count' => 16,
    ]);

    $this->actingAs($this->user)
        ->post("/diagrams/{$this->diagram->id}/apply-template/{$template->id}")
        ->assertRedirect();

    $version = $this->diagram->fresh()->currentVersion;
    expect($version)->not->toBeNull()
        ->and($version->objects_json)->toHaveCount(2)
        ->and($version->objects_json[0]['type'])->toBe('round_table_60');

    // re-generated ids so a second apply doesn't collide
    expect($version->objects_json[0]['id'])->not->toBe('tpl_1');
});

it('append mode preserves existing objects and adds the template ones', function () {
    $this->diagram->saveVersion([
        ['id' => 'existing_1', 'type' => 'chair', 'x' => 50, 'y' => 50, 'rotation' => 0],
    ]);

    $template = LayoutTemplate::query()->create([
        'space_id' => null,
        'name' => 'Add chairs',
        'objects_json' => [
            ['id' => 'tpl_1', 'type' => 'chair', 'x' => 100, 'y' => 100, 'rotation' => 0],
            ['id' => 'tpl_2', 'type' => 'chair', 'x' => 150, 'y' => 100, 'rotation' => 0],
        ],
        'object_count' => 2,
        'seat_count' => 0,
    ]);

    $this->actingAs($this->user)
        ->post("/diagrams/{$this->diagram->id}/apply-template/{$template->id}?mode=append")
        ->assertRedirect();

    expect($this->diagram->fresh()->currentVersion->objects_json)->toHaveCount(3);
});

it('returns the merged objects as JSON when the request expects JSON', function () {
    $this->diagram->saveVersion([
        ['id' => 'existing_1', 'type' => 'chair', 'x' => 50, 'y' => 50, 'rotation' => 0],
    ]);

    $template = LayoutTemplate::query()->create([
        'space_id' => null,
        'name' => 'Three chairs',
        'objects_json' => [
            ['id' => 'tpl_1', 'type' => 'chair', 'x' => 100, 'y' => 100, 'rotation' => 0],
            ['id' => 'tpl_2', 'type' => 'chair', 'x' => 150, 'y' => 100, 'rotation' => 0],
        ],
        'object_count' => 2,
        'seat_count' => 0,
    ]);

    $response = $this->actingAs($this->user)
        ->postJson("/diagrams/{$this->diagram->id}/apply-template/{$template->id}?mode=append");

    $response->assertOk()
        ->assertJsonStructure(['objects', 'mode', 'template' => ['id', 'name']])
        ->assertJsonPath('mode', 'append');

    expect($response->json('objects'))->toHaveCount(3)
        ->and($response->json('objects.0.id'))->toBe('existing_1');
});

it('rejects applying a template scoped to a different space', function () {
    $otherSpace = Space::factory()->create();
    $template = LayoutTemplate::query()->create([
        'space_id' => $otherSpace->id,
        'name' => 'Other space only',
        'objects_json' => [],
        'object_count' => 0,
        'seat_count' => 0,
    ]);

    $this->actingAs($this->user)
        ->post("/diagrams/{$this->diagram->id}/apply-template/{$template->id}")
        ->assertForbidden();
});

it('rejects applying a template to a locked diagram', function () {
    $this->diagram->forceFill(['locked_at' => now()])->save();
    $template = LayoutTemplate::factory()->global()->create();

    $this->actingAs($this->user)
        ->post("/diagrams/{$this->diagram->id}/apply-template/{$template->id}")
        ->assertStatus(423);
});

it('save-as captures the current objects as a new space-scoped template', function () {
    $payload = [
        'name' => 'My custom banquet',
        'category' => 'banquet',
        'description' => 'Captured from the editor',
        'objects' => [
            ['id' => 'a', 'type' => 'round_table_60', 'x' => 100, 'y' => 100, 'rotation' => 0, 'props' => ['seats' => 8]],
            ['id' => 'b', 'type' => 'round_table_60', 'x' => 200, 'y' => 100, 'rotation' => 0, 'props' => ['seats' => 8]],
        ],
    ];

    $response = $this->actingAs($this->user)
        ->postJson("/diagrams/{$this->diagram->id}/save-as-template", $payload);

    $response->assertOk()->assertJsonStructure(['id', 'name']);

    $template = LayoutTemplate::query()->where('name', 'My custom banquet')->firstOrFail();
    expect($template->space_id)->toBe($this->space->id)
        ->and($template->object_count)->toBe(2)
        ->and($template->seat_count)->toBe(16)
        ->and($template->created_by_user_id)->toBe($this->user->id);
});

it('LayoutTemplatesSeeder creates six global starter templates', function () {
    $this->seed(LayoutTemplatesSeeder::class);

    $globals = LayoutTemplate::query()->whereNull('space_id')->get();

    expect($globals)->toHaveCount(6)
        ->and($globals->pluck('category')->all())->toEqualCanonicalizing([
            'banquet', 'banquet', 'classroom', 'u_shape', 'booth_grid', 'reception',
        ]);
});

it('admin can view and delete layout templates', function () {
    $admin = grantSuperAdmin(User::factory()->create());
    $template = LayoutTemplate::factory()->global()->create(['name' => 'Throwaway']);

    $this->actingAs($admin)
        ->get('/admin/layout-templates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/layout-templates/index')
            ->has('templates'));

    $this->actingAs($admin)
        ->delete("/admin/layout-templates/{$template->id}")
        ->assertRedirect();

    expect(LayoutTemplate::query()->find($template->id))->toBeNull();
});

it('the diagram editor receives the available templates in its Inertia payload', function () {
    LayoutTemplate::factory()->global()->create(['name' => 'Global one']);
    LayoutTemplate::factory()->create(['space_id' => $this->space->id, 'name' => 'Space-specific']);
    LayoutTemplate::factory()->create(['name' => 'Different space']);

    $this->booking->spaces()->create([
        'space_id' => $this->space->id,
        'start_at' => $this->booking->start_at,
        'end_at' => $this->booking->end_at,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/bookings/{$this->booking->id}/diagram?space_id={$this->space->id}");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->component('bookings/diagram')
        ->where('templates', function ($templates) {
            $names = collect($templates)->pluck('name')->all();

            return in_array('Global one', $names, true)
                && in_array('Space-specific', $names, true)
                && ! in_array('Different space', $names, true);
        }));
});

it('orders the picker global templates first', function () {
    // globals first, then space-specific
    LayoutTemplate::factory()->create([
        'space_id' => $this->space->id,
        'category' => 'banquet',
        'name' => 'Space-specific banquet',
    ]);
    LayoutTemplate::factory()->global()->create([
        'category' => 'banquet',
        'name' => 'Global banquet',
    ]);
    LayoutTemplate::factory()->global()->create([
        'category' => 'banquet',
        'name' => 'Another global',
    ]);

    $this->booking->spaces()->create([
        'space_id' => $this->space->id,
        'start_at' => $this->booking->start_at,
        'end_at' => $this->booking->end_at,
    ]);

    $response = $this->actingAs($this->user)
        ->get("/bookings/{$this->booking->id}/diagram?space_id={$this->space->id}");

    $response->assertOk()->assertInertia(fn ($page) => $page
        ->where('templates', function ($templates) {
            $list = collect($templates);

            return $list[0]['is_global'] === true
                && $list[1]['is_global'] === true
                && $list[2]['is_global'] === false
                && $list[2]['name'] === 'Space-specific banquet';
        }));
});
