<?php

namespace Tests\Feature\Auth;

use App\Mail\AccountLifecycleMail;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SystemSettings\SystemSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AccountChangeNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function enable(string $recipients = 'isso@gov.test'): void
    {
        app(SystemSettings::class)->set('security.account_notifications_enabled', true);
        app(SystemSettings::class)->set('security.account_notification_recipients', $recipients);
    }

    private function audit(): AuditLogger
    {
        return app(AuditLogger::class);
    }

    public function test_a_disabled_account_notifies_the_configured_recipients(): void
    {
        Mail::fake();
        $this->enable();
        $admin = User::factory()->create();
        $target = User::factory()->create(['email' => 'casey@example.test']);

        $this->audit()->record('user.disabled', subject: $target, user: $admin);

        Mail::assertQueued(
            AccountLifecycleMail::class,
            fn (AccountLifecycleMail $mail) => $mail->action === 'disabled'
                && $mail->accountEmail === 'casey@example.test'
                && $mail->actorEmail === $admin->email
                && $mail->hasTo('isso@gov.test'),
        );
    }

    public function test_registration_notifies_with_the_created_action(): void
    {
        Mail::fake();
        $this->enable();
        $user = User::factory()->create();

        // no subject; the new user is the audit's user
        $this->audit()->record('user.registered', user: $user);

        Mail::assertQueued(
            AccountLifecycleMail::class,
            fn (AccountLifecycleMail $mail) => $mail->action === 'created'
                && $mail->accountEmail === $user->email
                && $mail->actorEmail === null,
        );
    }

    public function test_multiple_recipients_are_parsed(): void
    {
        Mail::fake();
        $this->enable('one@gov.test, two@gov.test ; bogus');

        $this->audit()->record('user.enabled', subject: User::factory()->create(), user: User::factory()->create());

        Mail::assertQueued(
            AccountLifecycleMail::class,
            fn (AccountLifecycleMail $mail) => $mail->hasTo('one@gov.test') && $mail->hasTo('two@gov.test'),
        );
    }

    public function test_nothing_is_sent_when_notifications_are_disabled(): void
    {
        Mail::fake();
        app(SystemSettings::class)->set('security.account_notification_recipients', 'isso@gov.test');
        // enabled flag left at its default (false)

        $this->audit()->record('user.disabled', subject: User::factory()->create(), user: User::factory()->create());

        Mail::assertNothingQueued();
    }

    public function test_nothing_is_sent_without_recipients(): void
    {
        Mail::fake();
        app(SystemSettings::class)->set('security.account_notifications_enabled', true);
        // recipients left empty

        $this->audit()->record('user.enabled', subject: User::factory()->create(), user: User::factory()->create());

        Mail::assertNothingQueued();
    }

    public function test_non_lifecycle_events_do_not_notify(): void
    {
        Mail::fake();
        $this->enable();

        $this->audit()->record('session.login', user: User::factory()->create());

        Mail::assertNothingQueued();
    }
}
