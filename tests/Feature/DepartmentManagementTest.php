<?php

use App\Models\Department;
use App\Models\EventOutline;
use App\Models\OutlineItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin(User::factory()->create());
});

it('lists departments', function () {
    Department::factory()->system()->create(['key' => 'setup', 'label' => 'Setup', 'color' => 'blue']);

    $this->actingAs($this->user)
        ->get('/admin/departments')
        ->assertInertia(fn ($page) => $page
            ->component('admin/departments/index')
            ->has('items', 1)
            ->has('colors'));
});

it('adds a custom department, deriving a slug key', function () {
    $this->actingAs($this->user)
        ->post('/admin/departments', ['label' => 'Medical Tent', 'color' => 'rose'])
        ->assertRedirect();

    $this->assertDatabaseHas('departments', [
        'key' => 'medical_tent',
        'label' => 'Medical Tent',
        'color' => 'rose',
        'is_system' => false,
        'is_active' => true,
    ]);
});

it('rejects a department whose slug already exists', function () {
    Department::factory()->create(['key' => 'catering', 'label' => 'Catering']);

    $this->actingAs($this->user)
        ->post('/admin/departments', ['label' => 'Catering', 'color' => 'amber'])
        ->assertSessionHasErrors('label');
});

it('rejects a color outside the palette', function () {
    $this->actingAs($this->user)
        ->post('/admin/departments', ['label' => 'Weird', 'color' => 'chartreuse'])
        ->assertSessionHasErrors('color');
});

it('renames + recolors a department', function () {
    $dept = Department::factory()->create(['label' => 'A/V', 'color' => 'indigo']);

    $this->actingAs($this->user)
        ->put("/admin/departments/{$dept->id}", ['label' => 'Audio/Visual', 'color' => 'violet'])
        ->assertRedirect();

    expect($dept->fresh())->label->toBe('Audio/Visual')->color->toBe('violet');
});

it('toggles active state', function () {
    $dept = Department::factory()->create(['is_active' => true]);

    $this->actingAs($this->user)->patch("/admin/departments/{$dept->id}/toggle")->assertRedirect();

    expect($dept->fresh()->is_active)->toBeFalse();
});

it('protects system departments from deletion', function () {
    $dept = Department::factory()->system()->create();

    $this->actingAs($this->user)
        ->delete("/admin/departments/{$dept->id}")
        ->assertSessionHasErrors('department');

    expect(Department::query()->find($dept->id))->not->toBeNull();
});

it('protects in-use departments from deletion', function () {
    $dept = Department::factory()->create(['key' => 'parking']);
    $outline = EventOutline::factory()->create();
    OutlineItem::factory()->create(['event_outline_id' => $outline->id, 'department' => 'parking']);

    $this->actingAs($this->user)
        ->delete("/admin/departments/{$dept->id}")
        ->assertSessionHasErrors('department');

    expect(Department::query()->find($dept->id))->not->toBeNull();
});

it('deletes a custom, unused department', function () {
    $dept = Department::factory()->create(['key' => 'spare']);

    $this->actingAs($this->user)
        ->delete("/admin/departments/{$dept->id}")
        ->assertRedirect();

    expect(Department::query()->find($dept->id))->toBeNull();
});
