<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnsurePasswordCurrentTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_expired_user_is_redirected_to_the_security_page(): void
    {
        config(['auth.password_policy.max_age_days' => 30]);
        $user = User::factory()->create(['password_changed_at' => now()->subDays(40)]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('security.edit'));
    }

    public function test_a_flagged_user_is_redirected_to_the_security_page(): void
    {
        $user = User::factory()->create(['force_password_change' => true]);

        $this->actingAs($user)->get('/dashboard')->assertRedirect(route('security.edit'));
    }

    public function test_a_current_user_is_not_redirected(): void
    {
        config(['auth.password_policy.max_age_days' => 30]);
        $user = User::factory()->create(['password_changed_at' => now()]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_no_op_when_the_policy_is_off(): void
    {
        // old password, but max_age_days defaults to 0 and no force flag
        $user = User::factory()->create(['password_changed_at' => now()->subYears(3)]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_a_flagged_user_may_still_reach_logout(): void
    {
        $user = User::factory()->create(['force_password_change' => true]);

        $this->actingAs($user)->post('/logout');

        $this->assertGuest();
    }
}
