<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crm_deals', function (Blueprint $table) {
            if (!Schema::hasColumn('crm_deals', 'risk_level')) {
                $table->string('risk_level', 20)->default('Low')->after('stage');
            }
            if (!Schema::hasColumn('crm_deals', 'health_score')) {
                $table->string('health_score', 20)->nullable()->after('risk_level');
            }
            if (!Schema::hasColumn('crm_deals', 'sentiment_score')) {
                $table->string('sentiment_score', 50)->nullable()->after('health_score');
            }
            if (!Schema::hasColumn('crm_deals', 'next_best_action')) {
                $table->text('next_best_action')->nullable()->after('sentiment_score');
            }
            if (!Schema::hasColumn('crm_deals', 'health_synced_at')) {
                $table->timestamp('health_synced_at')->nullable()->after('next_best_action');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_deals', function (Blueprint $table) {
            $table->dropColumn([
                'risk_level',
                'health_score',
                'sentiment_score',
                'next_best_action',
                'health_synced_at',
            ]);
        });
    }
};
