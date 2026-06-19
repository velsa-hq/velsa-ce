<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

// email is matched case-insensitively and stored lowercase; Postgres `=` and
// unique indexes are case-sensitive
it('authenticates when the email case differs from what is stored', function () {
    $user = User::factory()->create([
        'email' => 'casey@example.com',
        'password' => Hash::make('correct-horse-battery'),
    ]);

    $this->post('/login', [
        'email' => 'CASEY@Example.COM',
        'password' => 'correct-horse-battery',
    ]);

    $this->assertAuthenticatedAs($user);
})->group('security', 'auth');

it('stores admin-created emails in lowercase', function () {
    $this->actingAs(grantSuperAdmin())->post('/admin/users', [
        'name' => 'Mixed Case',
        'email' => 'Mixed.Case@Example.COM',
        'password' => 'correct-horse-battery-9',
    ]);

    expect(User::query()->where('email', 'mixed.case@example.com')->exists())->toBeTrue();
})->group('security', 'auth');

it('rejects an admin-created email that differs from an existing one only by case', function () {
    User::factory()->create(['email' => 'dupe@example.com']);

    $this->actingAs(grantSuperAdmin())
        ->post('/admin/users', [
            'name' => 'Dupe',
            'email' => 'DUPE@Example.com',
            'password' => 'correct-horse-battery-9',
        ])
        ->assertSessionHasErrors('email');

    expect(User::query()->where('email', 'dupe@example.com')->count())->toBe(1);
})->group('security', 'auth');
