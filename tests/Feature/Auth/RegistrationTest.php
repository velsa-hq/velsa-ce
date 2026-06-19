<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Self-registration is disabled; accounts come from an admin or SSO JIT only.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('register'));
    }

    public function test_registration_endpoints_are_unavailable(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password-123-ABC',
            'password_confirmation' => 'password-123-ABC',
        ])->assertNotFound();
    }
}
