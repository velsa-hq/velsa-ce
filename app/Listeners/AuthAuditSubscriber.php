<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Auth\SessionLimiter;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Session;

/**
 * Translates Laravel auth events into `audit_events` rows.
 *
 * Handlers are wired by event auto-discovery; do NOT also Event::subscribe()
 * this class or every handler double-fires.
 */
class AuthAuditSubscriber
{
    public function __construct(
        protected AuditLogger $auditLogger,
        protected SessionLimiter $sessionLimiter,
    ) {}

    public function handleLogin(Login $event): void
    {
        // reset idle-timeout clock; stale last_active_at would bounce the user
        // straight back to login. only User has these columns
        if ($event->user instanceof User) {
            // rotate last-logon timestamps to show previous sign-in (AC-9, APSC-DV-000580)
            $event->user->forceFill([
                'previous_login_at' => $event->user->last_login_at,
                'last_login_at' => now(),
                'last_active_at' => now(),
            ])->saveQuietly();

            $this->enforceConcurrentSessions($event->user);
        }

        $this->auditLogger->record(
            eventType: 'session.login',
            payload: [
                'guard' => $event->guard,
                'remember' => $event->remember,
                'authenticatable_type' => $event->user::class,
                'authenticatable_id' => $event->user->getAuthIdentifier(),
            ],
            user: $event->user instanceof User ? $event->user : null,
        );
    }

    /**
     * Audit concurrent logons (AU-12) and evict sessions past the cap
     * (AC-10, APSC-DV-000010).
     */
    private function enforceConcurrentSessions(User $user): void
    {
        $result = $this->sessionLimiter->enforceOnLogin(
            $user,
            Session::isStarted() ? Session::getId() : null,
        );

        if ($result['other_count'] > 0) {
            $this->auditLogger->record(
                eventType: 'session.concurrent',
                user: $user,
                payload: ['concurrent_sessions' => $result['other_count'] + 1],
            );
        }

        if ($result['evicted'] > 0) {
            $this->auditLogger->record(
                eventType: 'session.evicted',
                user: $user,
                payload: [
                    'evicted' => $result['evicted'],
                    'limit' => (int) config('auth.max_concurrent_sessions', 0),
                ],
            );
        }
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user === null) {
            return;
        }

        $this->auditLogger->record(
            eventType: 'session.logout',
            payload: [
                'guard' => $event->guard,
                'authenticatable_type' => $event->user::class,
                'authenticatable_id' => $event->user->getAuthIdentifier(),
            ],
            user: $event->user instanceof User ? $event->user : null,
        );
    }

    public function handleFailed(Failed $event): void
    {
        $this->auditLogger->record(
            eventType: 'session.failed',
            payload: [
                'guard' => $event->guard,
                'attempted_email' => is_array($event->credentials)
                    ? ($event->credentials['email'] ?? null)
                    : null,
            ],
            user: $event->user instanceof User ? $event->user : null,
        );
    }

    public function handleLockout(Lockout $event): void
    {
        $this->auditLogger->record(
            eventType: 'session.lockout',
            payload: [
                'attempted_email' => $event->request->input('email'),
            ],
        );
    }

    public function handleRegistered(Registered $event): void
    {
        $this->auditLogger->record(
            eventType: 'user.registered',
            user: $event->user,
        );
    }

    public function handleVerified(Verified $event): void
    {
        $this->auditLogger->record(
            eventType: 'user.email_verified',
            user: $event->user,
        );
    }

    public function handlePasswordReset(PasswordReset $event): void
    {
        $this->auditLogger->record(
            eventType: 'user.password_reset',
            user: $event->user,
        );
    }
}
