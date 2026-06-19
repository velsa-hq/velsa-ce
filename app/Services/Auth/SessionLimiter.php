<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Per-user concurrent-session limit against the database session store
 * (STIG APSC-DV-000010 / NIST AC-10); also reports concurrent logons (AU-12).
 *
 * Runs on the Login event before the new session row is persisted, so "other"
 * sessions are pre-existing ones on other devices: keep the newest (limit - 1)
 * and evict the rest, leaving room for the session being established.
 */
class SessionLimiter
{
    /**
     * @return array{other_count: int, evicted: int}
     */
    public function enforceOnLogin(User $user, ?string $currentSessionId): array
    {
        if (config('session.driver') !== 'database') {
            return ['other_count' => 0, 'evicted' => 0];
        }

        $others = $this->connection()
            ->table($this->table())
            ->where('user_id', $user->getAuthIdentifier())
            ->when($currentSessionId !== null, fn ($q) => $q->where('id', '!=', $currentSessionId))
            ->orderByDesc('last_activity')
            ->pluck('id');

        $otherCount = $others->count();
        $limit = (int) config('auth.max_concurrent_sessions', 0);

        if ($limit <= 0 || $otherCount < $limit) {
            return ['other_count' => $otherCount, 'evicted' => 0];
        }

        $toEvict = $others->slice($limit - 1)->values();

        $this->connection()
            ->table($this->table())
            ->whereIn('id', $toEvict->all())
            ->delete();

        return ['other_count' => $otherCount, 'evicted' => $toEvict->count()];
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection(config('session.connection'));
    }

    private function table(): string
    {
        return (string) config('session.table', 'sessions');
    }
}
