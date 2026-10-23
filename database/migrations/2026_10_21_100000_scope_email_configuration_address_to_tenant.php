<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // An SMTP sender address is unique within a tenant, not across all tenants (QA-09).
    public function up(): void
    {
        Schema::table('email_configurations', function (Blueprint $table) {
            $table->dropUnique('email_configurations_email_address_unique');
            $table->unique(['tenant_id', 'email_address'], 'email_configurations_tenant_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('email_configurations', function (Blueprint $table) {
            $table->dropUnique('email_configurations_tenant_email_unique');
            $table->unique('email_address');
        });
    }
};
