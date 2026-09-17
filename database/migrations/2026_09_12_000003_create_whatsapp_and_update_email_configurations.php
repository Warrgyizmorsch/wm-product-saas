<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1. Update email_configurations table to support multi-tenant, company, and branch
        if (Schema::hasTable('email_configurations')) {
            Schema::table('email_configurations', function (Blueprint $table) {
                if (!Schema::hasColumn('email_configurations', 'tenant_id')) {
                    $table->unsignedBigInteger('tenant_id')->default(1)->after('id')->index();
                }
                if (!Schema::hasColumn('email_configurations', 'company_id')) {
                    $table->foreignId('company_id')->nullable()->after('tenant_id')->constrained('companies')->nullOnDelete();
                }
                if (!Schema::hasColumn('email_configurations', 'branch_id')) {
                    $table->foreignId('branch_id')->nullable()->after('company_id')->constrained('branches')->nullOnDelete();
                }
            });
        }

        // 2. Create whatsapp_configurations table
        if (!Schema::hasTable('whatsapp_configurations')) {
            Schema::create('whatsapp_configurations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('session_key')->unique();
                $table->string('account_name')->nullable();
                $table->string('phone_number')->nullable();
                $table->string('status')->default('disconnected');
                $table->string('bridge_url')->default('http://127.0.0.1:3210');
                $table->string('bridge_token')->default('wm_erp_whatsapp_secret_token_2026');
                $table->boolean('is_default')->default(true);
                $table->boolean('is_active')->default(true);
                $table->json('settings')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'company_id', 'branch_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_configurations');

        if (Schema::hasTable('email_configurations')) {
            Schema::table('email_configurations', function (Blueprint $table) {
                if (Schema::hasColumn('email_configurations', 'branch_id')) {
                    $table->dropConstrainedForeignId('branch_id');
                }
                if (Schema::hasColumn('email_configurations', 'company_id')) {
                    $table->dropConstrainedForeignId('company_id');
                }
                if (Schema::hasColumn('email_configurations', 'tenant_id')) {
                    $table->dropColumn('tenant_id');
                }
            });
        }
    }
};
