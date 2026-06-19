<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LastLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_rotates_the_last_logon_timestamps(): void
    {
        $previousLogin = now()->subDays(3)->startOfSecond();
        $user = User::factory()->create([
            'last_login_at' => $previousLogin,
            'previous_login_at' => null,
        ]);

        event(new Login('web', $user, false));

        $user->refresh();
        // prior last_login rotates into previous_login
        $this->assertEquals($previousLogin->timestamp, $user->previous_login_at->timestamp);
        // last_login advances to now
        $this->assertTrue($user->last_login_at->isToday());
    }

    public function test_dashboard_shows_the_previous_sign_in_time(): void
    {
        $user = User::factory()->create([
            'previous_login_at' => now()->subDay(),
        ]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->whereNot('last_sign_in_at', null));
    }

    public function test_dashboard_omits_the_sign_in_line_on_first_login(): void
    {
        $user = User::factory()->create(['previous_login_at' => null]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('last_sign_in_at', null));
    }
}
