<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Services\Sso\SsoRegistry;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // explicit logout confirmation message on the login page (AC-12(2))
        $this->app->singleton(
            LogoutResponse::class,
            \App\Http\Responses\LogoutResponse::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);

        // null yields the generic failure so a disabled account isn't disclosed
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $request->string('email'))])
                ->first();

            if ($user === null || $user->isDisabled() || ! Hash::check($request->string('password'), $user->password)) {
                return null;
            }

            return $user;
        });
    }

    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
            'ssoProviders' => $this->ssoProvidersForLoginView(),
            'consentBanner' => $this->consentBanner(),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/reset-password', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/verify-email', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/two-factor-challenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/confirm-password'));
    }

    /**
     * Login consent banner config (STIG AC-8); null when disabled.
     *
     * @return array{text: string}|null
     */
    private function consentBanner(): ?array
    {
        $settings = $this->app->make(SystemSettings::class);

        if (! $settings->get('security.login_banner_enabled', false)) {
            return null;
        }

        return ['text' => (string) $settings->get('security.login_banner_text', '')];
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    private function ssoProvidersForLoginView(): array
    {
        $registry = $this->app->make(SsoRegistry::class);

        return collect($registry->enabled())
            ->map(fn ($driver) => [
                'key' => $driver->key(),
                'label' => $driver->label(),
                'url' => route('sso.redirect', ['provider' => $driver->key()]),
            ])
            ->values()
            ->all();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            // STIG APSC-DV-000530 / NIST AC-7: 3 attempts per 15 minutes
            return Limit::perMinutes(15, 3)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
