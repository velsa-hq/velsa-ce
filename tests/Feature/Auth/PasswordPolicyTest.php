<?php

namespace Tests\Feature\Auth;

use App\Models\PasswordHistory;
use App\Models\User;
use App\Services\Auth\PasswordPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function policy(): PasswordPolicy
    {
        return app(PasswordPolicy::class);
    }

    // default off

    public function test_everything_passes_when_the_policy_is_off(): void
    {
        $user = User::factory()->create(['password_changed_at' => now()->subYears(5)]);

        $this->assertSame([], $this->policy()->violations($user, 'password', 'password', false));
        $this->assertFalse($this->policy()->isExpired($user));
        $this->assertFalse($this->policy()->mustChange($user));
    }

    // minimum age

    public function test_minimum_age_blocks_a_too_recent_voluntary_change(): void
    {
        config(['auth.password_policy.min_age_hours' => 24]);
        $user = User::factory()->create(['password_changed_at' => now()->subHour()]);

        $this->assertNotEmpty($this->policy()->violations($user, 'BrandNew!23', 'old', false));
    }

    public function test_minimum_age_allows_a_change_after_the_window(): void
    {
        config(['auth.password_policy.min_age_hours' => 24]);
        $user = User::factory()->create(['password_changed_at' => now()->subHours(25)]);

        $this->assertSame([], $this->policy()->violations($user, 'BrandNew!23', 'old', false));
    }

    public function test_minimum_age_never_blocks_a_reset(): void
    {
        config(['auth.password_policy.min_age_hours' => 24]);
        $user = User::factory()->create(['password_changed_at' => now()->subHour()]);

        $this->assertSame([], $this->policy()->violations($user, 'BrandNew!23', null, true));
    }

    // composition

    public function test_composition_blocks_too_few_changed_characters(): void
    {
        config(['auth.password_policy.min_changed_chars' => 8]);
        $user = User::factory()->create();

        $this->assertNotEmpty($this->policy()->violations($user, 'SamePassword2', 'SamePassword1', false));
    }

    public function test_composition_allows_a_sufficiently_different_change(): void
    {
        config(['auth.password_policy.min_changed_chars' => 8]);
        $user = User::factory()->create();

        $this->assertSame([], $this->policy()->violations($user, 'ZZZZZZZZZZ99', 'aaaaaaaa', false));
    }

    public function test_composition_does_not_apply_on_reset(): void
    {
        config(['auth.password_policy.min_changed_chars' => 8]);
        $user = User::factory()->create();

        $this->assertSame([], $this->policy()->violations($user, 'short', null, true));
    }

    // reuse

    public function test_reuse_blocks_the_current_password(): void
    {
        config(['auth.password_policy.history_count' => 3]);
        $user = User::factory()->create(); // factory password is "password"

        $this->assertNotEmpty($this->policy()->violations($user, 'password', null, false));
    }

    public function test_reuse_blocks_a_recent_password_from_history(): void
    {
        config(['auth.password_policy.history_count' => 3]);
        $user = User::factory()->create();
        PasswordHistory::create(['user_id' => $user->id, 'password' => Hash::make('PriorPass!1')]);

        $this->assertNotEmpty($this->policy()->violations($user, 'PriorPass!1', null, false));
    }

    public function test_reuse_allows_a_fresh_password(): void
    {
        config(['auth.password_policy.history_count' => 3]);
        $user = User::factory()->create();

        $this->assertSame([], $this->policy()->violations($user, 'NeverUsed!99', null, false));
    }

    public function test_reuse_is_enforced_on_reset_too(): void
    {
        config(['auth.password_policy.history_count' => 3]);
        $user = User::factory()->create();

        $this->assertNotEmpty($this->policy()->violations($user, 'password', null, true));
    }

    // record + trim

    public function test_record_stamps_change_time_and_clears_the_force_flag(): void
    {
        $user = User::factory()->create([
            'force_password_change' => true,
            'password_changed_at' => now()->subDays(10),
        ]);

        $this->policy()->record($user, $user->password);

        $user->refresh();
        $this->assertFalse($user->force_password_change);
        $this->assertTrue($user->password_changed_at->isToday());
    }

    public function test_record_trims_history_to_the_configured_depth(): void
    {
        config(['auth.password_policy.history_count' => 2]);
        $user = User::factory()->create();

        foreach (['a', 'b', 'c'] as $p) {
            $this->policy()->record($user, Hash::make($p));
        }

        $this->assertSame(2, PasswordHistory::where('user_id', $user->id)->count());
    }

    public function test_record_keeps_no_history_when_reuse_is_off(): void
    {
        $user = User::factory()->create();

        $this->policy()->record($user, Hash::make('x'));

        $this->assertSame(0, PasswordHistory::where('user_id', $user->id)->count());
    }

    // expiry / mustChange

    public function test_expired_past_the_maximum_age(): void
    {
        config(['auth.password_policy.max_age_days' => 60]);
        $user = User::factory()->create(['password_changed_at' => now()->subDays(61)]);

        $this->assertTrue($this->policy()->isExpired($user));
        $this->assertTrue($this->policy()->mustChange($user));
    }

    public function test_not_expired_within_the_maximum_age(): void
    {
        config(['auth.password_policy.max_age_days' => 60]);
        $user = User::factory()->create(['password_changed_at' => now()->subDays(10)]);

        $this->assertFalse($this->policy()->isExpired($user));
    }

    public function test_force_flag_requires_a_change_regardless_of_age(): void
    {
        $user = User::factory()->create(['force_password_change' => true]);

        $this->assertTrue($this->policy()->mustChange($user));
    }
}
