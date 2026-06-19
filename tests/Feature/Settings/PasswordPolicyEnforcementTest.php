<?php

namespace Tests\Feature\Settings;

use App\Models\PasswordHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordPolicyEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_the_password_records_history_and_stamps_the_change(): void
    {
        config(['auth.password_policy.history_count' => 3]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'fresh-pass-9',
                'password_confirmation' => 'fresh-pass-9',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, PasswordHistory::where('user_id', $user->id)->count());
        $this->assertNotNull($user->refresh()->password_changed_at);
    }

    public function test_reuse_is_rejected_at_the_change_endpoint(): void
    {
        config(['auth.password_policy.history_count' => 3]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_minimum_age_is_rejected_at_the_change_endpoint(): void
    {
        config(['auth.password_policy.min_age_hours' => 24]);
        $user = User::factory()->create(['password_changed_at' => now()->subHour()]);

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'fresh-pass-9',
                'password_confirmation' => 'fresh-pass-9',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_policy_does_not_interfere_when_off(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->put(route('user-password.update'), [
                'current_password' => 'password',
                'password' => 'fresh-pass-9',
                'password_confirmation' => 'fresh-pass-9',
            ])
            ->assertSessionHasNoErrors();
    }
}
