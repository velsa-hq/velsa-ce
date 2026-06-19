<?php

use App\Models\AuditEvent;
use App\Models\User;
use App\Models\Venue;
use App\Services\Sso\MicrosoftEntraProvider;
use App\Services\Sso\SsoIdentity;
use App\Services\Sso\SsoProvider;
use App\Services\Sso\SsoProvisioningDisallowedException;
use App\Services\Sso\SsoRegistry;
use App\Services\Sso\SsoUserResolver;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpFoundation\RedirectResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('permission.testing', true);
    config()->set('sso.enabled', true);
    config()->set('sso.providers.microsoft.enabled', true);
    config()->set('sso.providers.microsoft.client_id', 'fake-client-id');
    config()->set('sso.providers.microsoft.client_secret', 'fake-client-secret');
    config()->set('sso.providers.microsoft.redirect', 'http://localhost/auth/sso/microsoft/callback');
    config()->set('sso.providers.microsoft.tenant', 'fake-tenant-id');

    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Fake provider returning a canned SsoIdentity from handleCallback().
 */
function fakeSsoProvider(SsoIdentity $identity): SsoProvider
{
    $fake = new class($identity) implements SsoProvider
    {
        public function __construct(public SsoIdentity $identity) {}

        public function key(): string
        {
            return 'microsoft';
        }

        public function label(): string
        {
            return 'Sign in with Microsoft';
        }

        public function isEnabled(): bool
        {
            return true;
        }

        public function redirect(): RedirectResponse
        {
            return redirect('https://login.microsoftonline.com/fake/oauth2/v2.0/authorize');
        }

        public function handleCallback(): SsoIdentity
        {
            return $this->identity;
        }
    };

    $registry = app(SsoRegistry::class);
    $registry->register($fake);

    return $fake;
}

it('exposes the sso provider on the login view when configured', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/login')
        ->where('ssoProviders.0.key', 'microsoft')
        ->where('ssoProviders.0.label', 'Sign in with Microsoft'));
});

it('hides the sso provider when sso is disabled', function () {
    config()->set('sso.enabled', false);

    $response = $this->get('/login');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('auth/login')
        ->where('ssoProviders', []));
});

it('404s the redirect for an unknown provider', function () {
    $this->get('/auth/sso/octopus')->assertNotFound();
});

it('404s the redirect when the provider has no credentials', function () {
    config()->set('sso.providers.microsoft.client_id', null);

    $this->get('/auth/sso/microsoft')->assertNotFound();
});

it('redirects an enabled provider to the IdP', function () {
    fakeSsoProvider(new SsoIdentity('microsoft', 'oid-1', 'someone@example.test'));

    $response = $this->get('/auth/sso/microsoft');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('login.microsoftonline.com');
});

it('jit-provisions a user on first callback', function () {
    config()->set('sso.provisioning', 'jit');
    Venue::factory()->create(['name' => 'Test Venue']);

    fakeSsoProvider(new SsoIdentity(
        provider: 'microsoft',
        providerUserId: 'oid-new-user-001',
        email: 'jit@example.test',
        name: 'JIT User',
    ));

    $response = $this->get('/auth/sso/microsoft/callback');

    $response->assertRedirect(route('dashboard'));
    $user = User::query()->where('email', 'jit@example.test')->firstOrFail();
    expect($user->sso_id)->toBe('oid-new-user-001')
        ->and($user->sso_provider)->toBe('microsoft')
        ->and($user->sso_provisioned_at)->not->toBeNull()
        ->and($this->isAuthenticated())->toBeTrue();
});

it('grants super_admin to a bootstrap-listed email on jit provision', function () {
    config()->set('sso.bootstrap_admin_emails', ['boss@example.test']);

    $venue = Venue::factory()->create();

    fakeSsoProvider(new SsoIdentity('microsoft', 'oid-boss', 'boss@example.test', 'Boss'));

    $this->get('/auth/sso/microsoft/callback')->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'boss@example.test')->firstOrFail();
    expect($user->roleAt($venue))->toBe('super_admin');
});

it('grants the default role to non-bootstrap users', function () {
    config()->set('sso.bootstrap_admin_emails', ['boss@example.test']);
    config()->set('sso.default_role', 'read_only');

    $venue = Venue::factory()->create();

    fakeSsoProvider(new SsoIdentity('microsoft', 'oid-2', 'someone@example.test'));

    $this->get('/auth/sso/microsoft/callback')->assertRedirect(route('dashboard'));

    $user = User::query()->where('email', 'someone@example.test')->firstOrFail();
    expect($user->roleAt($venue))->toBe('read_only');
});

it('refuses jit provisioning when mode is invite_only and no user matches', function () {
    config()->set('sso.provisioning', 'invite_only');

    fakeSsoProvider(new SsoIdentity('microsoft', 'oid-uninvited', 'nope@example.test'));

    $response = $this->get('/auth/sso/microsoft/callback');

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    expect(User::query()->where('email', 'nope@example.test')->exists())->toBeFalse();
});

it('adopts an existing local user by email match and stamps the sso_id', function () {
    $existing = User::factory()->create(['email' => 'returning@example.test']);

    fakeSsoProvider(new SsoIdentity('microsoft', 'oid-returning', 'returning@example.test'));

    $this->get('/auth/sso/microsoft/callback')->assertRedirect(route('dashboard'));

    expect($existing->fresh()->sso_id)->toBe('oid-returning')
        ->and($existing->fresh()->sso_provider)->toBe('microsoft')
        ->and($existing->fresh()->sso_provisioned_at)->not->toBeNull();
});

it('matches a returning user by sso_id even if email changed upstream', function () {
    User::factory()->create(['email' => 'old@example.test'])
        ->forceFill(['sso_provider' => 'microsoft', 'sso_id' => 'oid-stable-42'])
        ->save();

    fakeSsoProvider(new SsoIdentity('microsoft', 'oid-stable-42', 'new@example.test'));

    $this->get('/auth/sso/microsoft/callback')->assertRedirect(route('dashboard'));

    $user = User::query()->where('sso_id', 'oid-stable-42')->firstOrFail();
    expect($user->email)->toBe('new@example.test');
});

it('writes an auth.sso_login audit row with jit_provisioned flag', function () {
    Venue::factory()->create();
    fakeSsoProvider(new SsoIdentity('microsoft', 'oid-audit', 'audit@example.test'));

    $this->get('/auth/sso/microsoft/callback')->assertRedirect(route('dashboard'));

    $audit = AuditEvent::query()
        ->where('event_type', 'auth.sso_login')
        ->latest('id')
        ->first();

    expect($audit)->not->toBeNull()
        ->and($audit->payload_json['provider'])->toBe('microsoft')
        ->and($audit->payload_json['provider_user_id'])->toBe('oid-audit')
        ->and($audit->payload_json['email'])->toBe('audit@example.test')
        ->and($audit->payload_json['jit_provisioned'])->toBeTrue();
});

it('marks jit_provisioned false on repeat sign-in', function () {
    User::factory()->create(['email' => 'repeat@example.test'])
        ->forceFill(['sso_provider' => 'microsoft', 'sso_id' => 'oid-repeat'])
        ->save();

    fakeSsoProvider(new SsoIdentity('microsoft', 'oid-repeat', 'repeat@example.test'));

    $this->get('/auth/sso/microsoft/callback')->assertRedirect(route('dashboard'));

    $audit = AuditEvent::query()
        ->where('event_type', 'auth.sso_login')
        ->latest('id')
        ->first();
    expect($audit->payload_json['jit_provisioned'])->toBeFalse();
});

it('case-insensitively matches existing users by email', function () {
    $existing = User::factory()->create(['email' => 'Case@Example.Test']);

    app(SsoUserResolver::class)->resolve(new SsoIdentity(
        provider: 'microsoft',
        providerUserId: 'oid-case',
        email: 'case@example.test',
        name: 'Case Sensitive',
    ));

    expect($existing->fresh()->sso_id)->toBe('oid-case');
});

it('reports MicrosoftEntraProvider as disabled when credentials are missing', function () {
    config()->set('sso.providers.microsoft.client_id', null);

    $provider = app(MicrosoftEntraProvider::class);

    expect($provider->isEnabled())->toBeFalse();
});

it('throws SsoProvisioningDisallowedException when invite_only blocks a new user', function () {
    config()->set('sso.provisioning', 'invite_only');

    $resolver = app(SsoUserResolver::class);

    $resolver->resolve(new SsoIdentity('microsoft', 'oid-x', 'never@example.test'));
})->throws(SsoProvisioningDisallowedException::class);
