<?php

namespace App\Services\Security;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminPermission;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Contract;
use App\Models\Venue;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Gate;

/**
 * Verifies security functions are wired and operating (STIG SI-6 -
 * APSC-DV-002760/002770). Run via `php artisan security:self-test`. Each
 * check is a real misconfiguration tripwire (lost auth middleware, downgraded
 * hasher, debug on in prod), not a box-tick.
 */
class SecuritySelfTest
{
    public const PASS = 'pass';

    public const WARN = 'warn';

    public const FAIL = 'fail';

    /** Core models that must have an authorization policy. */
    private const POLICIED_MODELS = [Booking::class, Client::class, Venue::class, Contract::class];

    public function __construct(private readonly Router $router) {}

    /**
     * @return list<array{key: string, label: string, status: string, detail: string}>
     */
    public function run(): array
    {
        return [
            $this->checkAuthorizationEnforced(),
            $this->checkPoliciesRegistered(),
            $this->checkAdminRoutesGated(),
            $this->checkAuditChannel(),
            $this->checkPasswordHashing(),
            $this->checkIdleTimeout(),
            $this->checkDebugDisabledInProduction(),
            $this->checkSecureCookiesInProduction(),
        ];
    }

    public function passes(): bool
    {
        foreach ($this->run() as $check) {
            if ($check['status'] === self::FAIL) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{key: string, label: string, status: string, detail: string}
     */
    private function result(string $key, string $label, string $status, string $detail): array
    {
        return ['key' => $key, 'label' => $label, 'status' => $status, 'detail' => $detail];
    }

    private function checkAuthorizationEnforced(): array
    {
        // base controller must use AuthorizesRequests or $this->authorize() is a silent no-op
        $uses = class_uses(Controller::class) ?: [];
        $ok = in_array(AuthorizesRequests::class, $uses, true);

        return $this->result(
            'authorization_enforced',
            'Controller authorization wired',
            $ok ? self::PASS : self::FAIL,
            $ok ? 'Base controller uses AuthorizesRequests.' : 'Base controller is missing AuthorizesRequests - $this->authorize() is a no-op.',
        );
    }

    private function checkPoliciesRegistered(): array
    {
        $missing = array_values(array_filter(
            self::POLICIED_MODELS,
            fn (string $model) => Gate::getPolicyFor($model) === null,
        ));

        return $this->result(
            'policies_registered',
            'Authorization policies registered',
            $missing === [] ? self::PASS : self::FAIL,
            $missing === []
                ? 'All core models resolve a policy.'
                : 'No policy for: '.implode(', ', array_map('class_basename', $missing)),
        );
    }

    private function checkAdminRoutesGated(): array
    {
        $ungated = [];
        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            $name = $route->getName();
            if ($name === null || ! str_starts_with($name, 'admin.')) {
                continue;
            }
            if (! in_array(EnsureAdminPermission::class, $route->gatherMiddleware(), true)) {
                $ungated[] = $name;
            }
        }

        return $this->result(
            'admin_routes_gated',
            'Privileged routes require authorization',
            $ungated === [] ? self::PASS : self::FAIL,
            $ungated === []
                ? 'Every admin.* route carries EnsureAdminPermission.'
                : count($ungated).' admin route(s) missing the permission gate: '.implode(', ', array_slice($ungated, 0, 5)),
        );
    }

    private function checkAuditChannel(): array
    {
        $ok = config('logging.channels.audit') !== null;

        return $this->result(
            'audit_channel',
            'Audit log channel configured',
            $ok ? self::PASS : self::FAIL,
            $ok ? "The 'audit' log channel is defined." : "The 'audit' log channel is not configured - the audit trail is not shipped off-box.",
        );
    }

    private function checkPasswordHashing(): array
    {
        $driver = (string) config('hashing.driver');
        $ok = in_array($driver, ['bcrypt', 'argon', 'argon2id'], true);

        return $this->result(
            'password_hashing',
            'Strong password hashing',
            $ok ? self::PASS : self::FAIL,
            $ok ? "Hashing driver: {$driver}." : "Weak/unknown hashing driver: {$driver}.",
        );
    }

    private function checkIdleTimeout(): array
    {
        $minutes = (int) config('auth.idle_timeout_minutes', 0);

        return $this->result(
            'idle_timeout',
            'Idle session timeout enabled',
            $minutes > 0 ? self::PASS : self::WARN,
            $minutes > 0 ? "Sessions time out after {$minutes} min." : 'Idle timeout is disabled (0).',
        );
    }

    private function checkDebugDisabledInProduction(): array
    {
        if (! app()->isProduction()) {
            return $this->result('debug_off_prod', 'Debug disabled in production', self::PASS, 'Not production - debug may be on locally.');
        }

        $debug = (bool) config('app.debug');

        return $this->result(
            'debug_off_prod',
            'Debug disabled in production',
            $debug ? self::FAIL : self::PASS,
            $debug ? 'APP_DEBUG is ON in production - leaks stack traces/config to users.' : 'Debug is off.',
        );
    }

    private function checkSecureCookiesInProduction(): array
    {
        if (! app()->isProduction()) {
            return $this->result('secure_cookies_prod', 'Secure session cookies in production', self::PASS, 'Not production.');
        }

        $secure = (bool) config('session.secure');

        return $this->result(
            'secure_cookies_prod',
            'Secure session cookies in production',
            $secure ? self::PASS : self::WARN,
            $secure ? 'Session cookie is Secure-flagged.' : 'SESSION_SECURE_COOKIE is not set in production.',
        );
    }
}
