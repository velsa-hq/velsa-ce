<?php

namespace App\Providers;

use App\Services\Sso\MicrosoftEntraProvider;
use App\Services\Sso\SsoRegistry;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Azure\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;

/**
 * Wires Socialite, the microsoft-azure extender and the SSO registry.
 * Credentials live in config/sso.php.
 */
class SsoServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SsoRegistry::class, function ($app) {
            $registry = new SsoRegistry;
            $registry->register($app->make(MicrosoftEntraProvider::class));

            return $registry;
        });
    }

    public function boot(): void
    {
        // mirror config/sso.php to the services.microsoft-azure path where
        // Socialite's generic resolver looks; config/sso.php stays the source of truth
        config([
            'services.microsoft-azure' => [
                'client_id' => config('sso.providers.microsoft.client_id'),
                'client_secret' => config('sso.providers.microsoft.client_secret'),
                'redirect' => config('sso.providers.microsoft.redirect'),
                'tenant' => config('sso.providers.microsoft.tenant', 'common'),
            ],
        ]);

        // microsoft-azure isn't a built-in driver; it registers via this event
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite(
                'microsoft-azure',
                Provider::class,
            );
        });
    }
}
