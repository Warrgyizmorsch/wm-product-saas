<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_time_logs', function (Blueprint $table) {
            $table->index(['tenant_id', 'is_invoiced'], 'time_logs_tenant_invoiced_idx');
            $table->index(['tenant_id', 'invoice_id'], 'time_logs_tenant_invoice_idx');
        });
    }

    public function down(): void
    {
        Schema::table('project_time_logs', function (Blueprint $table) {
            $table->dropIndex('time_logs_tenant_invoiced_idx');
            $table->dropIndex('time_logs_tenant_invoice_idx');
        });
    }
};
