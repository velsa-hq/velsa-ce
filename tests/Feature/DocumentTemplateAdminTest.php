<?php

use App\Enums\TemplateKind;
use App\Models\Booking;
use App\Models\Contract;
use App\Models\DocumentTemplate;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = grantSuperAdmin(User::factory()->create());
});

it('lists templates grouped by kind with contract counts', function () {
    $venue = Venue::factory()->create(['name' => 'Pelican Cove']);
    DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Contract->value,
        'venue_id' => null,
        'name' => 'Global standard contract',
    ]);
    DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Proposal->value,
        'venue_id' => $venue->id,
        'name' => 'Pelican proposal',
    ]);

    $this->actingAs($this->user)
        ->get('/admin/document-templates')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/document-templates/index')
            ->has('templates', 2)
            ->has('kinds', 5));
});

it('creates a new template and redirects to its edit page', function () {
    $venue = Venue::factory()->create();

    $response = $this->actingAs($this->user)
        ->post('/admin/document-templates', [
            'kind' => TemplateKind::Contract->value,
            'venue_id' => $venue->id,
            'name' => 'Coral Reef contract',
            'body_html' => '<h1>{{venue.name}}</h1>',
            'is_active' => true,
        ]);

    $template = DocumentTemplate::query()->where('name', 'Coral Reef contract')->firstOrFail();
    $response->assertRedirect("/admin/document-templates/{$template->id}");

    expect($template->kind)->toBe(TemplateKind::Contract)
        ->and($template->venue_id)->toBe($venue->id)
        ->and($template->version)->toBe(1)
        ->and($template->is_active)->toBeTrue();
});

it('updates a template and bumps version when body changes', function () {
    $template = DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Contract->value,
        'body_html' => '<p>original</p>',
        'version' => 3,
    ]);

    $this->actingAs($this->user)
        ->put("/admin/document-templates/{$template->id}", [
            'kind' => TemplateKind::Contract->value,
            'venue_id' => null,
            'name' => $template->name,
            'body_html' => '<p>updated</p>',
            'is_active' => true,
        ])
        ->assertRedirect();

    $fresh = $template->fresh();
    expect($fresh->version)->toBe(4)
        ->and($fresh->body_html)->toBe('<p>updated</p>');
});

it('does not bump the version when only the name or scope changes', function () {
    $template = DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Contract->value,
        'body_html' => '<p>same</p>',
        'version' => 2,
    ]);

    $this->actingAs($this->user)
        ->put("/admin/document-templates/{$template->id}", [
            'kind' => TemplateKind::Contract->value,
            'venue_id' => null,
            'name' => 'Renamed only',
            'body_html' => '<p>same</p>',
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($template->fresh()->version)->toBe(2);
});

it('rejects an unknown kind on store', function () {
    $this->actingAs($this->user)
        ->post('/admin/document-templates', [
            'kind' => 'helipad',
            'name' => 'X',
            'body_html' => 'Y',
        ])
        ->assertSessionHasErrors('kind');
});

it('deletes a template without affecting historical contracts', function () {
    $template = DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Contract->value,
    ]);
    $booking = Booking::factory()->create();
    $contract = Contract::query()->create([
        'booking_id' => $booking->id,
        'template_id' => $template->id,
        'kind' => 'contract',
        'status' => 'draft',
        'total_cents' => 0,
        'rendered_html' => '<p>captured at draft time</p>',
    ]);

    $this->actingAs($this->user)
        ->delete("/admin/document-templates/{$template->id}")
        ->assertRedirect('/admin/document-templates');

    expect(DocumentTemplate::query()->find($template->id))->toBeNull()
        ->and($contract->fresh()->template_id)->toBeNull()
        ->and($contract->fresh()->rendered_html)->toBe('<p>captured at draft time</p>');
});

it('contract rendered_html survives a subsequent template body bump', function () {
    $template = DocumentTemplate::factory()->create([
        'kind' => TemplateKind::Contract->value,
        'body_html' => '<p>version 3 body</p>',
        'version' => 3,
    ]);
    $booking = Booking::factory()->create();
    $contract = Contract::query()->create([
        'booking_id' => $booking->id,
        'template_id' => $template->id,
        'kind' => 'contract',
        'status' => 'draft',
        'total_cents' => 0,
        'rendered_html' => '<p>version 3 body</p>',
    ]);

    $this->actingAs($this->user)
        ->put("/admin/document-templates/{$template->id}", [
            'kind' => TemplateKind::Contract->value,
            'name' => $template->name,
            'body_html' => '<p>version 4 body</p>',
            'is_active' => true,
        ])
        ->assertRedirect();

    $freshTemplate = $template->fresh();
    expect($freshTemplate->version)->toBe(4)
        ->and($freshTemplate->body_html)->toBe('<p>version 4 body</p>');

    expect($contract->fresh()->rendered_html)->toBe('<p>version 3 body</p>');
});

it('counts contracts per template on the index payload', function () {
    $template = DocumentTemplate::factory()->create();
    $booking = Booking::factory()->create();
    Contract::query()->create([
        'booking_id' => $booking->id,
        'template_id' => $template->id,
        'kind' => 'contract',
        'status' => 'draft',
        'total_cents' => 0,
    ]);
    Contract::query()->create([
        'booking_id' => $booking->id,
        'template_id' => $template->id,
        'kind' => 'contract',
        'status' => 'sent',
        'total_cents' => 0,
    ]);

    $this->actingAs($this->user)
        ->get('/admin/document-templates')
        ->assertInertia(fn ($page) => $page
            ->where('templates.0.contracts_count', 2));
});
