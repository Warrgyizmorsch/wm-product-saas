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
        Schema::table('leads', function (Blueprint $table) {
            $table->index(['tenant_id', 'call_date'], 'leads_tenant_call_date_idx');
            $table->index(['tenant_id', 'status'], 'leads_tenant_status_idx');
            $table->index(['tenant_id', 'email'], 'leads_tenant_email_idx');
            $table->index(['tenant_id', 'phone'], 'leads_tenant_phone_idx');
            $table->index(['tenant_id', 'company_name'], 'leads_tenant_company_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex('leads_tenant_call_date_idx');
            $table->dropIndex('leads_tenant_status_idx');
            $table->dropIndex('leads_tenant_email_idx');
            $table->dropIndex('leads_tenant_phone_idx');
            $table->dropIndex('leads_tenant_company_idx');
        });
    }
};
