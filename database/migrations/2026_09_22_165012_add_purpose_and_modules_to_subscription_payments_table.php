<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            // 'plan_switch' (existing flow, unchanged) or 'module_addon' (self-service
            // module install checkout) — see SubscriptionPaymentService::markPaid().
            // plan_id stays NOT NULL even for module_addon rows (set to the tenant's
            // plan at checkout time, just for record-keeping); it isn't used to decide
            // what happens on payment, `purpose`+`modules` is.
            $table->string('purpose')->default('plan_switch')->after('plan_id');
            $table->json('modules')->nullable()->after('purpose');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_payments', function (Blueprint $table) {
            $table->dropColumn(['purpose', 'modules']);
        });
    }
};
