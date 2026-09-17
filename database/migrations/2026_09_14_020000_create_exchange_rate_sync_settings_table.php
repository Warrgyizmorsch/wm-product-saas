<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-tenant auto-sync configuration for exchange rates.
 *
 * Its own table rather than a key in tenants.settings: TenantService::payload()
 * rebuilds that whole array on every tenant edit, which would silently wipe it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exchange_rate_sync_settings')) {
            return;
        }

        Schema::create('exchange_rate_sync_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('is_enabled')->default(false);
            $table->json('currencies')->nullable();   // foreign currency codes to sync
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_status')->nullable(); // success, partial, failed, skipped
            $table->text('last_error')->nullable();
            $table->json('unsupported')->nullable();  // codes the provider does not publish
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rate_sync_settings');
    }
};
