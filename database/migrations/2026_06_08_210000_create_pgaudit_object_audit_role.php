<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Object-level read auditing for the PostgreSQL STIG (pgaudit).
 *
 * pgaudit's object audit logging records statements that touch objects a
 * designated "audit role" has been granted access to. Pairing this role
 * (granted SELECT on the application schema) with `pgaudit.role = 'rds_pgaudit'`
 * in the RDS parameter group makes pgaudit log read access to application
 * data - the SELECT/"categories of information accessed" controls
 * (CD16-00-009500/009600/009700, AU-12) that pgaudit.log's write/ddl/role
 * classes don't cover.
 *
 * The role is NOLOGIN and is never granted to any user - it exists only as
 * the audit marker pgaudit reads. Postgres-only; a no-op on the sqlite test
 * driver (which has no role/GRANT concept).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'rds_pgaudit') THEN
                    CREATE ROLE rds_pgaudit NOLOGIN;
                END IF;
            END
            $$;
        SQL);

        DB::statement('GRANT SELECT ON ALL TABLES IN SCHEMA public TO rds_pgaudit');
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public GRANT SELECT ON TABLES TO rds_pgaudit');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Revoke the grants but leave the role: rds_pgaudit is RDS's reserved
        // pgaudit role and the parameter group may still reference it during a
        // rollback. Dropping an empty NOLOGIN role buys nothing and risks
        // touching a platform-managed role.
        DB::statement('ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE SELECT ON TABLES FROM rds_pgaudit');
        DB::statement('REVOKE ALL ON ALL TABLES IN SCHEMA public FROM rds_pgaudit');
    }
};
