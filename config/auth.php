<?php

use App\Models\Exhibitor;
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        // Portal guard for external exhibitor accounts. Exhibitors
        // authenticate via magic link (see App\Services\MagicLinkService);
        // there is no password on the model.
        'exhibitor' => [
            'driver' => 'session',
            'provider' => 'exhibitors',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        'exhibitors' => [
            'driver' => 'eloquent',
            'model' => Exhibitor::class,
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

    /*
    |--------------------------------------------------------------------------
    | Idle Session Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum minutes of inactivity before a logged-in user's session is
    | invalidated and they are redirected to login. Enforced by
    | IdleTimeoutMiddleware on every web request.
    |
    */

    'idle_timeout_minutes' => env('AUTH_IDLE_TIMEOUT_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Inactivity account-disable threshold
    |--------------------------------------------------------------------------
    |
    | Days of inactivity (no last_active_at update) after which the
    | users:disable-inactive command disables an account (STIG APSC-DV-000320 /
    | NIST AC-2(3)). Set to 0 to disable the behavior.
    |
    */

    'inactivity_disable_days' => (int) env('AUTH_INACTIVITY_DISABLE_DAYS', 35),

    /*
    |--------------------------------------------------------------------------
    | Concurrent session limit
    |--------------------------------------------------------------------------
    |
    | Maximum number of simultaneous sessions a single user may hold (STIG
    | APSC-DV-000010 / SRG-APP-000001 / NIST AC-10). When a user logs in past
    | this limit the oldest session(s) are evicted. Requires the database
    | session driver. Set to 0 to disable the limit (concurrent logons are
    | still audited regardless).
    |
    */

    'max_concurrent_sessions' => (int) env('AUTH_MAX_CONCURRENT_SESSIONS', 0),

    /*
    |--------------------------------------------------------------------------
    | Password policy (STIG IA-5(1) / CM-6)
    |--------------------------------------------------------------------------
    |
    | Optional, deployment-configurable password-lifetime and reuse controls.
    | Every check is OFF by default (0): NIST 800-63B deprecates forced
    | rotation and composition rules, so the application ships the capability
    | for environments bound to the DISA STIG without changing the default
    | posture. Enforced by App\Services\Auth\PasswordPolicy at every
    | password-change path and by EnsurePasswordCurrent on login.
    |
    |   min_age_hours      0 = off  Minimum password lifetime (APSC-DV-001760)
    |   max_age_days       0 = off  Maximum password lifetime (APSC-DV-001770)
    |   history_count      0 = off  Generations prohibited from reuse (APSC-DV-001780)
    |   min_changed_chars  0 = off  Chars that must differ on change (APSC-DV-001730)
    |
    */

    'password_policy' => [
        'min_age_hours' => (int) env('AUTH_PASSWORD_MIN_AGE_HOURS', 0),
        'max_age_days' => (int) env('AUTH_PASSWORD_MAX_AGE_DAYS', 0),
        'history_count' => (int) env('AUTH_PASSWORD_HISTORY_COUNT', 0),
        'min_changed_chars' => (int) env('AUTH_PASSWORD_MIN_CHANGED_CHARS', 0),
    ],

];
