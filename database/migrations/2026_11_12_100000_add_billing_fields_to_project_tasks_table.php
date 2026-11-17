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
        if (Schema::hasTable('project_tasks')) {
            Schema::table('project_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('project_tasks', 'billing_amount')) {
                    $table->decimal('billing_amount', 15, 2)->default(0)->after('status');
                }
                if (!Schema::hasColumn('project_tasks', 'is_invoiced')) {
                    $table->boolean('is_invoiced')->default(false)->after('billing_amount');
                }
                if (!Schema::hasColumn('project_tasks', 'invoice_id')) {
                    $table->unsignedBigInteger('invoice_id')->nullable()->after('is_invoiced');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('project_tasks')) {
            Schema::table('project_tasks', function (Blueprint $table) {
                if (Schema::hasColumn('project_tasks', 'invoice_id')) {
                    $table->dropColumn('invoice_id');
                }
                if (Schema::hasColumn('project_tasks', 'is_invoiced')) {
                    $table->dropColumn('is_invoiced');
                }
                if (Schema::hasColumn('project_tasks', 'billing_amount')) {
                    $table->dropColumn('billing_amount');
                }
            });
        }
    }
};
