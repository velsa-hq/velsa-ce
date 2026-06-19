<?php

use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('shows the consent banner on the login page when enabled', function () {
    app(SystemSettings::class)->set('security.login_banner_enabled', true);
    app(SystemSettings::class)->set('security.login_banner_text', 'Authorized use only - activity is monitored.');

    $this->get('/login')->assertInertia(
        fn (Assert $page) => $page
            ->component('auth/login')
            ->where('consentBanner.text', 'Authorized use only - activity is monitored.'),
    );
});

it('omits the consent banner by default', function () {
    $this->get('/login')->assertInertia(
        fn (Assert $page) => $page->component('auth/login')->where('consentBanner', null),
    );
});
