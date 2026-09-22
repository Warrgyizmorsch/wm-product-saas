<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gst_configurations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();

            // Provider Configuration
            $table->string('provider', 50)->default('sandbox'); // setu, cleartax, masters_india, cygnet, nic_direct, sandbox, custom
            $table->string('environment', 20)->default('sandbox'); // sandbox, production
            $table->string('auth_type', 30)->default('api_key'); // api_key, bearer_token, gsp_credentials
            $table->string('api_base_url')->nullable();
            
            // Credentials & Tokens
            $table->string('client_id')->nullable();
            $table->text('client_secret')->nullable();
            $table->text('api_token')->nullable(); // For Free Kit / Setu Bearer token / API Key
            $table->string('gstin_username')->nullable();
            $table->text('gstin_password')->nullable();
            
            // Seller GSTIN & Identity (Defaults fallback to Company/Tenant profile)
            $table->string('seller_gstin', 15)->nullable();
            $table->string('legal_name')->nullable();
            $table->string('trade_name')->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('location', 100)->nullable();
            $table->string('pincode', 10)->nullable();
            $table->string('state_code', 2)->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone', 20)->nullable();

            // Preferences
            $table->boolean('auto_generate_on_post')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->json('settings')->nullable();

            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gst_configurations');
    }
};
