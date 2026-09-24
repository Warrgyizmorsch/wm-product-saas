<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A mid-cycle change to a live subscription (TenantSubscriptionService::change):
        // an upgrade awaiting its prorated payment, or a downgrade scheduled for renewal.
        if (! Schema::hasColumn('tenant_subscriptions', 'pending_change')) {
            Schema::table('tenant_subscriptions', function (Blueprint $table) {
                $table->json('pending_change')->nullable()->after('modules');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenant_subscriptions', 'pending_change')) {
            Schema::table('tenant_subscriptions', function (Blueprint $table) {
                $table->dropColumn('pending_change');
            });
        }
    }
};
