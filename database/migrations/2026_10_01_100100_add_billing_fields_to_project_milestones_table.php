<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_milestones', function (Blueprint $table) {
            if (!Schema::hasColumn('project_milestones', 'billing_amount')) {
                $table->decimal('billing_amount', 15, 2)->nullable()->after('completion_percentage');
            }
            if (!Schema::hasColumn('project_milestones', 'is_invoiced')) {
                $table->boolean('is_invoiced')->default(false)->after('billing_amount');
            }
            if (!Schema::hasColumn('project_milestones', 'invoice_id')) {
                $table->foreignId('invoice_id')
                      ->nullable()
                      ->after('is_invoiced')
                      ->constrained('invoices')
                      ->nullOnDelete();
                $table->index(['tenant_id', 'is_invoiced'], 'milestones_tenant_invoiced_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_milestones', function (Blueprint $table) {
            if (Schema::hasColumn('project_milestones', 'invoice_id')) {
                $table->dropForeign(['invoice_id']);
                $table->dropIndex('milestones_tenant_invoiced_idx');
                $table->dropColumn('invoice_id');
            }
            if (Schema::hasColumn('project_milestones', 'is_invoiced')) {
                $table->dropColumn('is_invoiced');
            }
            if (Schema::hasColumn('project_milestones', 'billing_amount')) {
                $table->dropColumn('billing_amount');
            }
        });
    }
};
