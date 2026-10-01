<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('invoices', 'project_id')) {
                $table->foreignId('project_id')
                      ->nullable()
                      ->after('customer_id')
                      ->constrained('projects')
                      ->nullOnDelete();
                $table->index(['tenant_id', 'project_id'], 'invoices_tenant_project_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            if (Schema::hasColumn('invoices', 'project_id')) {
                $table->dropForeign(['project_id']);
                $table->dropIndex('invoices_tenant_project_idx');
                $table->dropColumn('project_id');
            }
        });
    }
};
