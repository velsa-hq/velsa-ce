<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Services\Sso\EntraGroupRoleResolver;
use App\Services\Sso\EntraGroupsClient;
use App\Services\Sso\SsoProvider;
use App\Services\Sso\SsoProvisioningDisallowedException;
use App\Services\Sso\SsoRegistry;
use App\Services\Sso\SsoUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Staff-side SSO endpoint. Only Microsoft (Entra ID) is wired today; the
 * {provider} route param leaves room for additional drivers.
 */
class SsoController extends Controller
{
    public function __construct(
        protected SsoRegistry $registry,
        protected SsoUserResolver $resolver,
        protected AuditLogger $auditLogger,
        protected EntraGroupsClient $entraGroups,
        protected EntraGroupRoleResolver $entraGroupRoles,
    ) {}

    public function redirect(string $provider): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return $this->resolveOrFail($provider)->redirect();
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        $driver = $this->resolveOrFail($provider);

        try {
            $identity = $driver->handleCallback();
            $user = $this->resolver->resolve($identity);
        } catch (SsoProvisioningDisallowedException) {
            return redirect()->route('login')->withErrors([
                'email' => 'Your account is not provisioned. Contact an administrator.',
            ]);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors([
                'email' => 'Sign-in failed. Please try again.',
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // re-apply Entra group -> role on every sign-in so admin mapping
        // changes take effect at next login; Entra-specific, others skip
        $appliedRoles = [];
        if ($identity->provider === 'microsoft' && $identity->accessToken !== null) {
            $groupIds = $this->entraGroups->fetchGroupIds($identity->accessToken);
            if ($groupIds !== []) {
                $appliedRoles = $this->entraGroupRoles->applyForUser($user, $groupIds);
            }
        }

        $this->auditLogger->record(
            eventType: 'auth.sso_login',
            subject: $user,
            payload: [
                'provider' => $identity->provider,
                'provider_user_id' => $identity->providerUserId,
                'email' => $identity->email,
                'jit_provisioned' => $user->wasRecentlyCreated,
                'entra_groups_applied' => count($appliedRoles),
            ],
        );

        return redirect()->intended(route('dashboard'));
    }

    protected function resolveOrFail(string $provider): SsoProvider
    {
        try {
            $driver = $this->registry->get($provider);
        } catch (Throwable) {
            throw new NotFoundHttpException;
        }

        if (! $driver->isEnabled()) {
            throw new NotFoundHttpException;
        }

        return $driver;
    }
}
