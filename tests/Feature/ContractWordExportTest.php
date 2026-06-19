<?php

use App\Models\Contract;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('exports a contract as an editable Word document', function () {
    $admin = grantSuperAdmin();
    $contract = Contract::factory()->create(['rendered_html' => '<p>Master Rental Agreement Body</p>']);

    $response = $this->actingAs($admin)->get("/contracts/{$contract->id}/document.doc");

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/msword');
    expect($response->headers->get('content-disposition'))->toContain("{$contract->reference}.doc");
    $response->assertSee('Master Rental Agreement Body', false);
});

it('404s when the contract has no rendered content', function () {
    $admin = grantSuperAdmin();
    $contract = Contract::factory()->create(['rendered_html' => null]);

    $this->actingAs($admin)->get("/contracts/{$contract->id}/document.doc")->assertNotFound();
});
