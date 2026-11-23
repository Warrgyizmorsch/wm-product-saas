<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * FRS 2.3 / Task C-18: Party Merge Tool.
     * Records merged_into_id, merged_at, and merged_by on duplicate customers and vendors
     * so merged records remain safely archived with a complete audit trail and redirect pointer.
     */
    public function up(): void
    {
        if (Schema::hasTable('customers') && ! Schema::hasColumn('customers', 'merged_into_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->unsignedBigInteger('merged_into_id')->nullable()->after('status');
                $table->timestamp('merged_at')->nullable()->after('merged_into_id');
                $table->unsignedBigInteger('merged_by')->nullable()->after('merged_at');
                $table->index(['tenant_id', 'merged_into_id'], 'customers_tenant_merged_into_idx');
            });
        }

        if (Schema::hasTable('vendors') && ! Schema::hasColumn('vendors', 'merged_into_id')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->unsignedBigInteger('merged_into_id')->nullable()->after('status');
                $table->timestamp('merged_at')->nullable()->after('merged_into_id');
                $table->unsignedBigInteger('merged_by')->nullable()->after('merged_at');
                $table->index(['tenant_id', 'merged_into_id'], 'vendors_tenant_merged_into_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'merged_into_id')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->dropIndex('customers_tenant_merged_into_idx');
                $table->dropColumn(['merged_into_id', 'merged_at', 'merged_by']);
            });
        }

        if (Schema::hasTable('vendors') && Schema::hasColumn('vendors', 'merged_into_id')) {
            Schema::table('vendors', function (Blueprint $table) {
                $table->dropIndex('vendors_tenant_merged_into_idx');
                $table->dropColumn(['merged_into_id', 'merged_at', 'merged_by']);
            });
        }
    }
};
