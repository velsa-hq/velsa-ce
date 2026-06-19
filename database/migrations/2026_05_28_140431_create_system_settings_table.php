<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * System-level configuration that admins can change at runtime
     * without redeploying. The registry (App\Services\SystemSettings)
     * declares the canonical set of keys with metadata; this table
     * stores the per-deployment overrides.
     *
     * Secret values (BluePay merchant id, DocuSign integration key,
     * SSO client secret, etc.) are encrypted at rest using the app
     * key. The is_secret flag drives both encryption on save AND
     * masking on read in the admin UI.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->boolean('is_secret')->default(false);
            $table->foreignId('updated_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
