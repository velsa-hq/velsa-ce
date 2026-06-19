<?php

namespace App\Console\Commands;

use App\Services\AuditLogger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Remove time-bound role assignments whose expiry has passed (temporary access
 * for shifts/contractors). Runs hourly; permanent assignments (expires_at null)
 * are untouched.
 */
#[Signature('roles:expire')]
#[Description('Remove role assignments whose temporary expiry has passed')]
class ExpireRoleAssignments extends Command
{
    public function __construct(private AuditLogger $audit)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $expired = DB::table('model_has_roles')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired role assignments.');

            return self::SUCCESS;
        }

        foreach ($expired as $row) {
            DB::table('model_has_roles')
                ->where('role_id', $row->role_id)
                ->where('model_type', $row->model_type)
                ->where('model_id', $row->model_id)
                ->where('venue_id', $row->venue_id)
                ->delete();

            $this->audit->record(
                eventType: 'user.role_expired',
                payload: [
                    'model_id' => $row->model_id,
                    'role_id' => $row->role_id,
                    'venue_id' => $row->venue_id,
                    'expired_at' => $row->expires_at,
                ],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->info("Removed {$expired->count()} expired role assignment(s).");

        return self::SUCCESS;
    }
}
