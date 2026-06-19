<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Venue;
use App\Support\SafeMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

/**
 * Seeds the demo administrator (super_admin) plus ~14 named staff across
 * the four teams (Events, Sales, Ops, Finance) so RBAC and audit logs
 * have real subjects. Each staff user gets a firstname.lastname email and
 * a role at every active venue (or at one venue for venue-scoped roles).
 *
 * Passwords come from the environment so no credential ships in source:
 * DEMO_USER_PASSWORD (admin) and DEMO_STAFF_PASSWORD (staff). Both fall
 * back to "password" for local dev only; deployed must set strong values.
 *
 * Idempotent: keyed on email; re-running re-asserts the same roles.
 */
class SentinelBayUsersSeeder extends Seeder
{
    /**
     * @var list<array{
     *     name: string,
     *     email_prefix: string,
     *     team: string,
     *     role: string,
     *     venues?: list<string>,
     * }>
     */
    protected array $roster = [
        // --- Events directorate ---
        ['name' => 'Jordan Pierce', 'email_prefix' => 'jordan.pierce', 'team' => 'Events', 'role' => 'org_admin'],
        ['name' => 'Maya Chen', 'email_prefix' => 'maya.chen', 'team' => 'Events', 'role' => 'event_coordinator'],
        ['name' => 'Eli Rodriguez', 'email_prefix' => 'eli.rodriguez', 'team' => 'Events', 'role' => 'event_coordinator'],
        ['name' => 'Sam Park', 'email_prefix' => 'sam.park', 'team' => 'Events', 'role' => 'event_coordinator'],

        // --- Sales ---
        ['name' => 'Rachel Tate', 'email_prefix' => 'rachel.tate', 'team' => 'Sales', 'role' => 'sales_manager'],
        ['name' => 'David Kim', 'email_prefix' => 'david.kim', 'team' => 'Sales', 'role' => 'sales_rep'],
        ['name' => 'Aaliyah Brooks', 'email_prefix' => 'aaliyah.brooks', 'team' => 'Sales', 'role' => 'sales_rep'],

        // --- Operations ---
        ['name' => 'Carlos Mendez', 'email_prefix' => 'carlos.mendez', 'team' => 'Ops', 'role' => 'ops_lead'],
        ['name' => 'Priya Shah', 'email_prefix' => 'priya.shah', 'team' => 'Ops', 'role' => 'ops_lead', 'venues' => ['sentinel-bay-sports-recreation-complex']],
        ['name' => 'Marcus Holloway', 'email_prefix' => 'marcus.holloway', 'team' => 'Ops', 'role' => 'contractor'],
        ['name' => 'Lin Zhao', 'email_prefix' => 'lin.zhao', 'team' => 'Ops', 'role' => 'contractor'],

        // --- Finance ---
        ['name' => 'Hannah Wallace', 'email_prefix' => 'hannah.wallace', 'team' => 'Finance', 'role' => 'finance'],
        ['name' => 'Ben Foster', 'email_prefix' => 'ben.foster', 'team' => 'Finance', 'role' => 'finance'],
        ['name' => 'Olivia Tran', 'email_prefix' => 'olivia.tran', 'team' => 'Finance', 'role' => 'finance'],
    ];

    public function run(): void
    {
        $venues = Venue::query()->active()->get()->keyBy('slug');

        if ($venues->isEmpty()) {
            $this->command?->warn('SentinelBayUsersSeeder: no active venues. Run SentinelBayVenuesSeeder first.');

            return;
        }

        // credentials from env so no password ships in source; outside
        // local/testing the env vars are required, no hard-coded fallback
        // (STIG APSC-DV-003110 / APSC-DV-003280, NIST IA-5(7), SA-4(5))
        $adminPassword = Hash::make($this->demoPassword('DEMO_USER_PASSWORD'));
        $staffPassword = Hash::make($this->demoPassword('DEMO_STAFF_PASSWORD'));

        // primary demo administrator (super_admin), promoted at every active venue
        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@sentinelbay.ca.gov'],
            [
                'name' => 'Sentinel Bay Administrator',
                'password' => $adminPassword,
                'email_verified_at' => now(),
                'last_active_at' => now(),
            ],
        );
        foreach ($venues->values() as $venue) {
            $admin->assignRoleAt($venue, 'super_admin');
        }

        // public demo login (non-admin demo role) with a publicly-shared
        // password; seeded only when safe mode is on AND DEMO_PUBLIC_PASSWORD
        // is set, so a non-inert instance never carries a known credential
        $publicPassword = $this->demoPassword('DEMO_PUBLIC_PASSWORD', required: false);
        if (SafeMode::enabled() && $publicPassword !== '') {
            $demoUser = User::query()->firstOrCreate(
                ['email' => 'demo@sentinelbay.ca.gov'],
                [
                    'name' => 'Velsa Demo',
                    'password' => Hash::make($publicPassword),
                    'email_verified_at' => now(),
                    'last_active_at' => now(),
                ],
            );
            foreach ($venues->values() as $venue) {
                $demoUser->assignRoleAt($venue, 'demo');
            }
        }

        $created = 0;
        foreach ($this->roster as $entry) {
            $user = User::query()->firstOrCreate(
                ['email' => $entry['email_prefix'].'@sentinelbay.ca.gov'],
                [
                    'name' => $entry['name'],
                    'password' => $staffPassword,
                    'email_verified_at' => now(),
                    'last_active_at' => now()->subDays(random_int(0, 14)),
                ],
            );

            $targets = isset($entry['venues'])
                ? $venues->only($entry['venues'])->values()
                : $venues->values();

            foreach ($targets as $venue) {
                $user->assignRoleAt($venue, $entry['role']);
            }

            $created++;
        }

        $this->command?->info("SentinelBayUsersSeeder: ensured admin@sentinelbay.ca.gov (super_admin) + {$created} County staff across Events / Sales / Ops / Finance.");
    }

    /**
     * Resolve a demo password from env. Local/testing falls back to
     * "password"; every other environment must supply a non-empty value
     * or seeding aborts.
     */
    private function demoPassword(string $key, bool $required = true): string
    {
        $value = (string) env($key, '');

        if ($value !== '') {
            return $value;
        }

        // optional credential: absent means "don't seed it" rather than abort
        if (! $required) {
            return '';
        }

        if (app()->environment('local', 'testing')) {
            return 'password';
        }

        throw new RuntimeException(
            "{$key} must be set (to a strong value) before seeding demo users outside local/testing - refusing to seed a default password."
        );
    }
}
