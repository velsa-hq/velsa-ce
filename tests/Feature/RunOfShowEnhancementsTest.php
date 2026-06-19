<?php

use App\Models\Booking;
use App\Models\Department;
use App\Models\EventOutline;
use App\Models\OutlineItem;
use App\Models\OutlineItemTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin(User::factory()->create());
});

it('adds, toggles, and removes a checklist task on an outline item', function () {
    $outline = EventOutline::query()->create(['booking_id' => Booking::factory()->create()->id]);
    $item = OutlineItem::factory()->create(['event_outline_id' => $outline->id]);

    $this->actingAs($this->user)
        ->post("/outline-items/{$item->id}/tasks", ['label' => 'Mic check'])
        ->assertRedirect();

    $task = $item->tasks()->first();
    expect($task)->not->toBeNull()
        ->and($task->label)->toBe('Mic check')
        ->and($task->is_done)->toBeFalse();

    $this->actingAs($this->user)->patch("/outline-item-tasks/{$task->id}/toggle")->assertRedirect();
    expect($task->fresh())->is_done->toBeTrue()->done_at->not->toBeNull();

    $this->actingAs($this->user)->patch("/outline-item-tasks/{$task->id}/toggle")->assertRedirect();
    expect($task->fresh())->is_done->toBeFalse()->done_at->toBeNull();

    $this->actingAs($this->user)->delete("/outline-item-tasks/{$task->id}")->assertRedirect();
    expect($item->tasks()->count())->toBe(0);
});

it('creates an item with a checklist via storeItem', function () {
    Department::factory()->system()->create(['key' => 'av', 'label' => 'A/V']);
    $booking = Booking::factory()->create();

    $this->actingAs($this->user)
        ->post("/bookings/{$booking->id}/outline/items", [
            'title' => 'A/V sound check',
            'department' => 'av',
            'scheduled_at' => now()->addDay()->toDateTimeString(),
            'duration_minutes' => 45,
            'description' => '**Full** audio pass',
            'checklist' => ['Mic check', 'Playback test', 'Set levels', ''],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $item = OutlineItem::query()->where('title', 'A/V sound check')->first();
    expect($item)->not->toBeNull()
        ->and($item->department)->toBe('av')
        ->and($item->duration_minutes)->toBe(45)
        ->and($item->tasks()->count())->toBe(3) // blank dropped
        ->and($item->tasks()->orderBy('position')->first()->label)->toBe('Mic check');
});

it('renders the item description as Markdown HTML and exposes templates', function () {
    OutlineItemTemplate::factory()->create(['label' => 'Crew setup']);
    $booking = Booking::factory()->create();
    $outline = EventOutline::query()->create(['booking_id' => $booking->id]);
    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'department' => 'setup',
        'description' => '**bold** and a [link](https://example.com)',
    ]);

    $this->actingAs($this->user)
        ->get("/bookings/{$booking->id}/outline")
        ->assertInertia(fn (Assert $page) => $page
            ->component('bookings/outline')
            ->where('items.0.description_html', fn ($html) => str_contains((string) $html, '<strong>bold</strong>'))
            ->has('items.0.tasks')
            ->has('item_templates', 1));
});

it('escapes raw HTML in Markdown descriptions', function () {
    $booking = Booking::factory()->create();
    $outline = EventOutline::query()->create(['booking_id' => $booking->id]);
    OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'department' => 'setup',
        'description' => 'hi <script>alert(1)</script>',
    ]);

    $this->actingAs($this->user)
        ->get("/bookings/{$booking->id}/outline")
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.0.description_html', fn ($html) => ! str_contains((string) $html, '<script>')));
});

it('manages run-of-show templates from the admin', function () {
    Department::factory()->system()->create(['key' => 'av', 'label' => 'A/V']);

    $this->actingAs($this->user)
        ->get('/admin/outline-item-templates')
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/outline-item-templates/index')
            ->has('templates')
            ->has('departments'));

    $this->actingAs($this->user)
        ->post('/admin/outline-item-templates', [
            'label' => 'Gala setup',
            'department' => 'av',
            'default_duration_minutes' => 60,
            'description' => 'Stage the gala',
            'checklist' => ['Drape', 'Centerpieces', ''],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $template = OutlineItemTemplate::query()->where('label', 'Gala setup')->first();
    expect($template)->not->toBeNull()
        ->and($template->checklist)->toBe(['Drape', 'Centerpieces']) // blank dropped
        ->and($template->is_system)->toBeFalse();

    $this->actingAs($this->user)
        ->put("/admin/outline-item-templates/{$template->id}", [
            'label' => 'Gala setup',
            'default_duration_minutes' => 90,
            'checklist' => ['Drape'],
        ])
        ->assertRedirect();
    expect($template->fresh()->default_duration_minutes)->toBe(90);

    $this->actingAs($this->user)->delete("/admin/outline-item-templates/{$template->id}")->assertRedirect();
    expect(OutlineItemTemplate::query()->find($template->id))->toBeNull();
});

it('renders the run-of-show as a printable PDF run sheet', function () {
    Pdf::fake();
    $booking = Booking::factory()->create();
    $outline = EventOutline::query()->create(['booking_id' => $booking->id]);
    $item = OutlineItem::factory()->create([
        'event_outline_id' => $outline->id,
        'department' => 'setup',
        'title' => 'Crew setup',
        'description' => '**Stage** the room',
    ]);
    $item->tasks()->create(['label' => 'Drape', 'position' => 0]);

    $response = $this->actingAs($this->user)->get("/bookings/{$booking->id}/outline.pdf");

    $response->assertOk();
    Pdf::assertRespondedWithPdf(function ($pdf) use ($booking) {
        expect($pdf->viewName)->toBe('pdf.run-of-show')
            ->and($pdf->viewData['booking']->id)->toBe($booking->id)
            ->and($pdf->viewData['items'])->toHaveCount(1)
            ->and($pdf->viewData['items'][0]['title'])->toBe('Crew setup')
            ->and($pdf->viewData['items'][0]['tasks'][0]['label'])->toBe('Drape');

        return true;
    });
});

it('requires authentication on the run-of-show PDF', function () {
    $booking = Booking::factory()->create();

    $this->get("/bookings/{$booking->id}/outline.pdf")->assertRedirect(route('login'));
});

it('protects system templates from deletion', function () {
    $template = OutlineItemTemplate::factory()->system()->create();

    $this->actingAs($this->user)
        ->delete("/admin/outline-item-templates/{$template->id}")
        ->assertSessionHasErrors('template');

    expect(OutlineItemTemplate::query()->find($template->id))->not->toBeNull();
});
