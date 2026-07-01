<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL 16 STIG schema hardening.
 * - CD16-00-007700: revoke the implicit CREATE on schema public from PUBLIC so
 *   unprivileged roles cannot create objects there (the PG15+ default, made
 *   explicit + asserted). The app/migration role is the Aurora master and holds
 *   CREATE explicitly, so this does not affect it.
 * - CD16-00-000100: the rds_pgaudit role is NOLOGIN (object-audit only); cap its
 *   connection limit to 0 as defense-in-depth against it ever holding a session.
 * pgsql-only; no-op on other drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('REVOKE CREATE ON SCHEMA public FROM PUBLIC');
        DB::statement("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname='rds_pgaudit') THEN EXECUTE 'ALTER ROLE rds_pgaudit CONNECTION LIMIT 0'; END IF; END \$\$");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }
        DB::statement('GRANT CREATE ON SCHEMA public TO PUBLIC');
        DB::statement("DO \$\$ BEGIN IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname='rds_pgaudit') THEN EXECUTE 'ALTER ROLE rds_pgaudit CONNECTION LIMIT -1'; END IF; END \$\$");
    }
};
