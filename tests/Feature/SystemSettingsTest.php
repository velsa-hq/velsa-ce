<?php

use App\Http\Controllers\Admin\SystemSettingController;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\SystemSettings\ConfigOverlay;
use App\Services\SystemSettings\SettingDefinition;
use App\Services\SystemSettings\SystemSettings;
use App\Services\SystemSettings\SystemSettingsRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Crypt;

uses(RefreshDatabase::class);

beforeEach(function () {
    // reset cache so reads don't carry state across tests
    app(SystemSettings::class)->forget();
});

// ---------- Service ----------

it('falls back to the registry default when no DB value or env exists', function () {
    expect(app(SystemSettings::class)->get('branding.app_title'))
        ->toBe('Your organization');
});

it('returns a DB value over the registry default', function () {
    app(SystemSettings::class)->set('branding.app_title', 'County Customer');

    expect(app(SystemSettings::class)->get('branding.app_title'))
        ->toBe('County Customer');
});

it('coerces integer-typed settings on read', function () {
    app(SystemSettings::class)->set('defaults.fiscal_year_start_month', 7);

    $value = app(SystemSettings::class)->get('defaults.fiscal_year_start_month');
    expect($value)->toBeInt()->toBe(7);
});

it('returns null for an unregistered key (no exception leaks)', function () {
    expect(app(SystemSettings::class)->get('not.a.real.key'))->toBeNull();
});

it('refuses writes to an unregistered key', function () {
    app(SystemSettings::class)->set('not.a.real.key', 'whatever');
})->throws(RuntimeException::class, 'Unknown system setting');

it('encrypts secret values at rest', function () {
    // register a secret on the fly
    $registry = app(SystemSettingsRegistry::class);
    $reflection = new ReflectionClass($registry);
    $defs = $reflection->getProperty('definitions');
    $defs->setAccessible(true);
    $existing = $defs->getValue($registry);
    $existing['test.secret'] = new SettingDefinition(
        key: 'test.secret',
        category: 'defaults',
        label: 'Test secret',
        isSecret: true,
    );
    $defs->setValue($registry, $existing);
    app(SystemSettings::class)->forget();

    app(SystemSettings::class)->set('test.secret', 'hunter2');

    $row = SystemSetting::query()->where('key', 'test.secret')->first();
    expect($row)->not->toBeNull()
        ->and($row->value)->not->toBe('hunter2')
        ->and(Crypt::decryptString($row->value))->toBe('hunter2');

    expect(app(SystemSettings::class)->get('test.secret'))->toBe('hunter2');
});

it('masks secret values in allValuesMasked()', function () {
    $registry = app(SystemSettingsRegistry::class);
    $reflection = new ReflectionClass($registry);
    $defs = $reflection->getProperty('definitions');
    $defs->setAccessible(true);
    $existing = $defs->getValue($registry);
    $existing['test.secret2'] = new SettingDefinition(
        key: 'test.secret2',
        category: 'defaults',
        label: 'Test secret 2',
        isSecret: true,
    );
    $defs->setValue($registry, $existing);
    app(SystemSettings::class)->forget();

    app(SystemSettings::class)->set('test.secret2', 'top-secret');

    $masked = app(SystemSettings::class)->allValuesMasked();
    expect($masked['test.secret2'])->toBe('••••••••');

    $unmasked = app(SystemSettings::class)->allValues();
    expect($unmasked['test.secret2'])->toBe('top-secret');
});

it('falls back to env when the DB has no row and an envKey is declared', function () {
    // inject via the env repository so env() picks it up at read time
    // (raw $_ENV writes don't reach env() after boot)
    $registry = app(SystemSettingsRegistry::class);
    $reflection = new ReflectionClass($registry);
    $defs = $reflection->getProperty('definitions');
    $defs->setAccessible(true);
    $existing = $defs->getValue($registry);
    $existing['test.from_env'] = new SettingDefinition(
        key: 'test.from_env',
        category: 'defaults',
        label: 'From env',
        envKey: 'TEST_FROM_ENV',
        default: 'registry-default',
    );
    $defs->setValue($registry, $existing);
    app(SystemSettings::class)->forget();

    Env::getRepository()->set('TEST_FROM_ENV', 'env-value');

    expect(app(SystemSettings::class)->get('test.from_env'))->toBe('env-value');

    Env::getRepository()->clear('TEST_FROM_ENV');
});

it('caches reads forever and invalidates on write', function () {
    app(SystemSettings::class)->set('branding.app_title', 'First value');
    expect(app(SystemSettings::class)->get('branding.app_title'))->toBe('First value');

    // mutate the DB directly to prove the cache exists
    SystemSetting::query()
        ->where('key', 'branding.app_title')
        ->update(['value' => 'Bypassed cache']);

    expect(app(SystemSettings::class)->get('branding.app_title'))->toBe('First value');

    // through the service - the cache invalidates
    app(SystemSettings::class)->set('branding.app_title', 'Second value');
    expect(app(SystemSettings::class)->get('branding.app_title'))->toBe('Second value');
});

// ---------- Admin controller ----------

it('renders the system settings index for an authenticated admin', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    $response = $this->actingAs($admin)->get('/admin/system-settings');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/system-settings/index')
        ->has('categories')
        ->has('values'));
});

it('saves a value via the update endpoint', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    $response = $this->actingAs($admin)->put('/admin/system-settings', [
        'values' => [
            'branding.app_title' => 'Customer Name',
            'branding.app_subtitle' => 'Customer Subtitle',
        ],
    ]);

    $response->assertRedirect();
    expect(app(SystemSettings::class)->get('branding.app_title'))
        ->toBe('Customer Name');
});

it('rejects a Meilisearch host on the link-local metadata range', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    $this->actingAs($admin)
        ->from('/admin/system-settings')
        ->put('/admin/system-settings', [
            'values' => ['integrations.meilisearch.host' => 'http://169.254.170.2:7700'],
        ])
        ->assertSessionHasErrors('values.integrations.meilisearch.host');
});

it('accepts a normal Meilisearch host', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    $this->actingAs($admin)
        ->put('/admin/system-settings', [
            'values' => ['integrations.meilisearch.host' => 'http://meilisearch.internal:7700'],
        ])
        ->assertSessionDoesntHaveErrors('values.integrations.meilisearch.host');
});

it('keeps a secret unchanged when the KEEP_CURRENT sentinel is sent back', function () {
    // promote a defaults setting to secret to test the keep-current path
    $registry = app(SystemSettingsRegistry::class);
    $reflection = new ReflectionClass($registry);
    $defs = $reflection->getProperty('definitions');
    $defs->setAccessible(true);
    $existing = $defs->getValue($registry);
    $existing['test.api_key'] = new SettingDefinition(
        key: 'test.api_key',
        category: 'defaults',
        label: 'API key',
        isSecret: true,
    );
    $defs->setValue($registry, $existing);
    app(SystemSettings::class)->forget();

    app(SystemSettings::class)->set('test.api_key', 'original-secret');

    $admin = grantSuperAdmin(User::factory()->create());
    $this->actingAs($admin)->put('/admin/system-settings', [
        'values' => [
            'test.api_key' => SystemSettingController::KEEP_CURRENT,
        ],
    ])->assertRedirect();

    expect(app(SystemSettings::class)->get('test.api_key'))->toBe('original-secret');
});

it('clears a value back to default when an empty string is submitted', function () {
    app(SystemSettings::class)->set('branding.app_title', 'Will be cleared');
    expect(app(SystemSettings::class)->get('branding.app_title'))->toBe('Will be cleared');

    $admin = grantSuperAdmin(User::factory()->create());
    $this->actingAs($admin)->put('/admin/system-settings', [
        'values' => [
            'branding.app_title' => '',
        ],
    ])->assertRedirect();

    expect(app(SystemSettings::class)->get('branding.app_title'))
        ->toBe('Your organization');
});

it('silently ignores unknown keys posted via update', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    $this->actingAs($admin)->put('/admin/system-settings', [
        'values' => [
            'this.is.not.a.real.key' => 'whatever',
            'branding.app_title' => 'Legitimate save',
        ],
    ])->assertRedirect();

    expect(app(SystemSettings::class)->get('branding.app_title'))
        ->toBe('Legitimate save');
    expect(SystemSetting::query()->where('key', 'this.is.not.a.real.key')->exists())
        ->toBeFalse();
});

it('requires authentication on the index endpoint', function () {
    $this->get('/admin/system-settings')->assertRedirect('/login');
});

it('stamps updated_by_user_id on writes', function () {
    $admin = grantSuperAdmin(User::factory()->create());
    $this->actingAs($admin)->put('/admin/system-settings', [
        'values' => ['branding.app_title' => 'Attributed'],
    ])->assertRedirect();

    $row = SystemSetting::query()->where('key', 'branding.app_title')->first();
    expect($row->updated_by_user_id)->toBe($admin->id);
});

it('exposes branding props on every Inertia page via shared data', function () {
    app(SystemSettings::class)->set('branding.app_title', 'Shared via middleware');

    $admin = grantSuperAdmin(User::factory()->create());
    $response = $this->actingAs($admin)->get('/dashboard');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('branding.app_title', 'Shared via middleware'));
});

it('refuses to glob a path-traversal stock background folder', function () {
    // a tampered folder must not list .webp files outside the public web root
    app(SystemSettings::class)->set('branding.stock_background_folder', '../../storage');

    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('stockBackgrounds', []));
});

// ---------- Integrations: overlay + SSO + DocuSign ----------

it('overlays Microsoft client_id onto sso.providers.microsoft.client_id', function () {
    app(SystemSettings::class)->set('integrations.microsoft.client_id', 'admin-set-client-id');

    app(ConfigOverlay::class)->apply(app('config'));

    expect(config('sso.providers.microsoft.client_id'))->toBe('admin-set-client-id');
});

it('overlays the Microsoft client_secret with encryption at rest', function () {
    app(SystemSettings::class)->set('integrations.microsoft.client_secret', 'super-secret-value');

    // DB row is encrypted
    $row = SystemSetting::query()->where('key', 'integrations.microsoft.client_secret')->firstOrFail();
    expect($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toBe('super-secret-value');

    // overlay surfaces the cleartext on the config side
    app(ConfigOverlay::class)->apply(app('config'));
    expect(config('sso.providers.microsoft.client_secret'))->toBe('super-secret-value');
});

it('overlays DocuSign credentials onto services.docusign.*', function () {
    app(SystemSettings::class)->set('integrations.docusign.integration_key', 'ik-from-admin');
    app(SystemSettings::class)->set('integrations.docusign.account_id', 'acct-12345');
    app(SystemSettings::class)->set('integrations.docusign.enabled', '1');

    app(ConfigOverlay::class)->apply(app('config'));

    expect(config('services.docusign.integration_key'))->toBe('ik-from-admin')
        ->and(config('services.docusign.account_id'))->toBe('acct-12345')
        ->and(config('services.docusign.enabled'))->toBeTrue();
});

it('overlays Postmark credentials and selects the Postmark transport', function () {
    app(SystemSettings::class)->set('integrations.postmark.token', 'tok-test-12345');
    app(SystemSettings::class)->set('integrations.postmark.from_email', 'sender@example.test');
    app(SystemSettings::class)->set('integrations.mail.transport', 'postmark');
    app(SystemSettings::class)->set('branding.app_subtitle', 'Velsa Notifications');

    app(ConfigOverlay::class)->apply(app('config'));

    expect(config('services.postmark.key'))->toBe('tok-test-12345')
        ->and(config('mail.from.address'))->toBe('sender@example.test')
        ->and(config('mail.from.name'))->toBe('Velsa Notifications')
        ->and(config('mail.default'))->toBe('postmark');
});

it('routes mail through Microsoft 365 authenticated SMTP when selected', function () {
    config()->set('mail.default', 'log');
    app(SystemSettings::class)->set('integrations.mail.transport', 'microsoft365');
    app(SystemSettings::class)->set('integrations.microsoft365.username', 'events@county.test');
    app(SystemSettings::class)->set('integrations.microsoft365.password', 'app-pass-123');
    app(SystemSettings::class)->set('integrations.microsoft365.from_email', 'no-reply@county.test');
    app(SystemSettings::class)->set('branding.app_subtitle', 'County Events');

    app(ConfigOverlay::class)->apply(app('config'));

    expect(config('mail.default'))->toBe('smtp')
        ->and(config('mail.mailers.smtp.host'))->toBe('smtp.office365.com')
        ->and(config('mail.mailers.smtp.port'))->toBe(587)
        ->and(config('mail.mailers.smtp.username'))->toBe('events@county.test')
        ->and(config('mail.mailers.smtp.password'))->toBe('app-pass-123')
        ->and(config('mail.from.address'))->toBe('no-reply@county.test')
        ->and(config('mail.from.name'))->toBe('County Events');
});

it('leaves the default mailer alone when the transport is default', function () {
    config()->set('mail.default', 'log');
    app(SystemSettings::class)->set('integrations.postmark.token', 'tok-test-12345');
    app(SystemSettings::class)->set('integrations.postmark.from_email', 'sender@example.test');
    app(SystemSettings::class)->set('integrations.mail.transport', 'default');

    app(ConfigOverlay::class)->apply(app('config'));

    // token + from still flow through (so switching transports later
    // works without a re-save) but the default mailer is left at env
    expect(config('services.postmark.key'))->toBe('tok-test-12345')
        ->and(config('mail.from.address'))->toBe('sender@example.test')
        ->and(config('mail.default'))->toBe('log');
});

it('explodes the SSO bootstrap-admin emails CSV into a config array', function () {
    app(SystemSettings::class)->set(
        'integrations.sso.bootstrap_admin_emails',
        'a@example.test, b@example.test ,c@example.test',
    );

    app(ConfigOverlay::class)->apply(app('config'));

    expect(config('sso.bootstrap_admin_emails'))
        ->toBe(['a@example.test', 'b@example.test', 'c@example.test']);
});

it('refuses to overwrite Microsoft client_secret when the sentinel is sent back', function () {
    app(SystemSettings::class)->set('integrations.microsoft.client_secret', 'original-secret');

    $admin = grantSuperAdmin(User::factory()->create());
    $this->actingAs($admin)->put('/admin/system-settings', [
        'values' => [
            'integrations.microsoft.client_secret' => SystemSettingController::KEEP_CURRENT,
            'integrations.microsoft.client_id' => 'overwriting-client-id',
        ],
    ])->assertRedirect();

    expect(app(SystemSettings::class)->get('integrations.microsoft.client_secret'))
        ->toBe('original-secret');
    expect(app(SystemSettings::class)->get('integrations.microsoft.client_id'))
        ->toBe('overwriting-client-id');
});

it('masks the DocuSign secret_key on the admin index payload', function () {
    app(SystemSettings::class)->set('integrations.docusign.secret_key', 'docusign-secret-payload');

    $admin = grantSuperAdmin(User::factory()->create());
    $response = $this->actingAs($admin)->get('/admin/system-settings');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('admin/system-settings/index')
        ->where('values', fn ($values) => ($values['integrations.docusign.secret_key'] ?? null) === '••••••••'
        )
    );
});

it('preserves existing config when overlay finds no DB row', function () {
    config()->set('sso.providers.microsoft.client_id', 'env-default-value');

    app(ConfigOverlay::class)->apply(app('config'));

    expect(config('sso.providers.microsoft.client_id'))->toBe('env-default-value');
});

// ---------- Integration URL SSRF guard ----------

it('rejects an off-domain docusign base_uri', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    $this->actingAs($admin)
        ->from('/admin/system-settings')
        ->put('/admin/system-settings', [
            'values' => ['integrations.docusign.base_uri' => 'https://attacker.example.com/restapi'],
        ])
        ->assertSessionHasErrors('values.integrations.docusign.base_uri');

    // setting untouched - still the registry default
    expect(app(SystemSettings::class)->get('integrations.docusign.base_uri'))
        ->toBe('https://demo.docusign.net/restapi');
});

it('rejects a non-https docusign base_uri', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    $this->actingAs($admin)
        ->from('/admin/system-settings')
        ->put('/admin/system-settings', [
            'values' => ['integrations.docusign.oauth_base' => 'http://account-d.docusign.com'],
        ])
        ->assertSessionHasErrors('values.integrations.docusign.oauth_base');
});

it('accepts a docusign URL on an allowed host', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    $this->actingAs($admin)->put('/admin/system-settings', [
        'values' => ['integrations.docusign.base_uri' => 'https://na3.docusign.net/restapi'],
    ])->assertRedirect();

    expect(app(SystemSettings::class)->get('integrations.docusign.base_uri'))
        ->toBe('https://na3.docusign.net/restapi');
});

it('rejects an integer setting outside its declared min/max', function () {
    $admin = grantSuperAdmin(User::factory()->create());

    // TTL is bounded 1-90; out-of-range values must fail the save
    $this->actingAs($admin)->put('/admin/system-settings', [
        'values' => ['security.portal_magic_link_ttl_days' => '99999'],
    ])->assertSessionHasErrors('values.security.portal_magic_link_ttl_days');

    $this->actingAs($admin)->put('/admin/system-settings', [
        'values' => ['security.portal_magic_link_ttl_days' => '0'],
    ])->assertSessionHasErrors('values.security.portal_magic_link_ttl_days');

    // nothing persisted on the rejected writes
    expect(app(SystemSettings::class)->getStored('security.portal_magic_link_ttl_days'))->toBeNull();

    // an in-range value saves fine
    $this->actingAs($admin)->put('/admin/system-settings', [
        'values' => ['security.portal_magic_link_ttl_days' => '14'],
    ])->assertRedirect()->assertSessionHasNoErrors();
    expect((int) app(SystemSettings::class)->get('security.portal_magic_link_ttl_days'))->toBe(14);
});

// ---------- Integrations: payment gateway selector ----------

it('overlays BluePay credentials and selects the bluepay processor', function () {
    app(SystemSettings::class)->set('integrations.payments.gateway', 'bluepay');
    app(SystemSettings::class)->set('integrations.bluepay.merchant_id', '123412341234');
    app(SystemSettings::class)->set('integrations.bluepay.secret_key', 'bp-secret');
    app(SystemSettings::class)->set('integrations.bluepay.mode', 'TEST');
    app(SystemSettings::class)->set('integrations.bluepay.hash_type', 'HMAC_SHA512');

    app(ConfigOverlay::class)->apply(app('config'));

    expect(config('payments.processor'))->toBe('bluepay')
        ->and(config('payments.bluepay.merchant_id'))->toBe('123412341234')
        ->and(config('payments.bluepay.secret_key'))->toBe('bp-secret')
        ->and(config('payments.bluepay.mode'))->toBe('TEST')
        ->and(config('payments.bluepay.hash_type'))->toBe('HMAC_SHA512');
});

it('encrypts the BluePay secret key at rest', function () {
    app(SystemSettings::class)->set('integrations.bluepay.secret_key', 'bp-secret-plain');

    $row = SystemSetting::query()->where('key', 'integrations.bluepay.secret_key')->firstOrFail();
    expect($row->is_secret)->toBeTrue()
        ->and($row->value)->not->toBe('bp-secret-plain');
});

it('overlays Stripe credentials and selects the stripe processor', function () {
    app(SystemSettings::class)->set('integrations.payments.gateway', 'stripe');
    app(SystemSettings::class)->set('integrations.stripe.secret', 'sk_test_xyz');
    app(SystemSettings::class)->set('integrations.stripe.currency', 'usd');

    app(ConfigOverlay::class)->apply(app('config'));

    expect(config('payments.processor'))->toBe('stripe')
        ->and(config('payments.stripe.secret'))->toBe('sk_test_xyz')
        ->and(config('payments.stripe.currency'))->toBe('usd');
});

it('leaves the payment processor at the env default when gateway is none', function () {
    config()->set('payments.processor', 'fake');
    app(SystemSettings::class)->set('integrations.payments.gateway', 'none');

    app(ConfigOverlay::class)->apply(app('config'));

    expect(config('payments.processor'))->toBe('fake');
});
