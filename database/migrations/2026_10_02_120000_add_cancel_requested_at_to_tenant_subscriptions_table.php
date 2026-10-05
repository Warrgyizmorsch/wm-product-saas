<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The tenant cancelled (TenantSubscriptionService::cancel): the gateway stops
        // renewing at current_end, access continues until then.
        if (! Schema::hasColumn('tenant_subscriptions', 'cancel_requested_at')) {
            Schema::table('tenant_subscriptions', function (Blueprint $table) {
                $table->timestamp('cancel_requested_at')->nullable()->after('grace_ends_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenant_subscriptions', 'cancel_requested_at')) {
            Schema::table('tenant_subscriptions', function (Blueprint $table) {
                $table->dropColumn('cancel_requested_at');
            });
        }
    }
};
