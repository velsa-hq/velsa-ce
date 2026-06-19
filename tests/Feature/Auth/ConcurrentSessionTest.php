<?php

namespace Tests\Feature\Auth;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Auth\SessionLimiter;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ConcurrentSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // limiter only acts on the database session driver
        config(['session.driver' => 'database']);
    }

    private function seedSession(User $user, string $id, int $lastActivity): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'x',
            'last_activity' => $lastActivity,
        ]);
    }

    public function test_it_evicts_oldest_sessions_beyond_the_cap(): void
    {
        config(['auth.max_concurrent_sessions' => 2]);
        $user = User::factory()->create();

        $this->seedSession($user, 'old', 100);
        $this->seedSession($user, 'mid', 200);
        $this->seedSession($user, 'new', 300);

        $result = app(SessionLimiter::class)->enforceOnLogin($user, 'incoming');

        // 3 existing + incoming = 4; cap 2, keep newest, evict 2 oldest
        $this->assertSame(3, $result['other_count']);
        $this->assertSame(2, $result['evicted']);
        $remaining = DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all();
        $this->assertSame(['new'], $remaining);
    }

    public function test_it_keeps_all_sessions_when_under_the_cap(): void
    {
        config(['auth.max_concurrent_sessions' => 5]);
        $user = User::factory()->create();
        $this->seedSession($user, 'a', 100);
        $this->seedSession($user, 'b', 200);

        $result = app(SessionLimiter::class)->enforceOnLogin($user, 'incoming');

        $this->assertSame(0, $result['evicted']);
        $this->assertSame(2, DB::table('sessions')->where('user_id', $user->id)->count());
    }

    public function test_a_zero_cap_never_evicts(): void
    {
        config(['auth.max_concurrent_sessions' => 0]);
        $user = User::factory()->create();
        $this->seedSession($user, 'a', 100);
        $this->seedSession($user, 'b', 200);

        $result = app(SessionLimiter::class)->enforceOnLogin($user, 'incoming');

        $this->assertSame(0, $result['evicted']);
        $this->assertSame(2, $result['other_count']);
    }

    public function test_it_is_a_no_op_on_non_database_drivers(): void
    {
        config(['session.driver' => 'array', 'auth.max_concurrent_sessions' => 1]);
        $user = User::factory()->create();
        $result = app(SessionLimiter::class)->enforceOnLogin($user, 'incoming');

        $this->assertSame(['other_count' => 0, 'evicted' => 0], $result);
    }

    public function test_login_audits_a_concurrent_logon_even_when_limiting_is_off(): void
    {
        config(['auth.max_concurrent_sessions' => 0]);
        $user = User::factory()->create();
        $this->seedSession($user, 'elsewhere', 100);

        event(new Login('web', $user, false));

        $this->assertSame(
            1,
            AuditEvent::query()->where('event_type', 'session.concurrent')->count(),
        );
        // off, so nothing evicted
        $this->assertSame(0, AuditEvent::query()->where('event_type', 'session.evicted')->count());
    }

    public function test_login_audits_an_eviction_when_over_the_cap(): void
    {
        config(['auth.max_concurrent_sessions' => 1]);
        $user = User::factory()->create();
        $this->seedSession($user, 'old', 100);
        $this->seedSession($user, 'newer', 200);

        event(new Login('web', $user, false));

        $event = AuditEvent::query()->where('event_type', 'session.evicted')->first();
        $this->assertNotNull($event);
        $this->assertSame(2, $event->payload_json['evicted']);
    }

    public function test_a_solo_login_records_no_concurrent_event(): void
    {
        config(['auth.max_concurrent_sessions' => 0]);
        $user = User::factory()->create();

        event(new Login('web', $user, false));

        $this->assertSame(0, AuditEvent::query()->where('event_type', 'session.concurrent')->count());
    }
}
