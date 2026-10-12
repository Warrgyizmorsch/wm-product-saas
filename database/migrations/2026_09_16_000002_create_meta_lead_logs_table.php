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
        if (!Schema::hasTable('meta_lead_logs')) {
            Schema::create('meta_lead_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('meta_configuration_id')->nullable()->index();
                
                $table->string('leadgen_id')->index();
                $table->string('page_id')->nullable()->index();
                $table->string('form_id')->nullable()->index();
                $table->string('ad_id')->nullable();
                $table->string('adgroup_id')->nullable();
                $table->string('campaign_id')->nullable();
                
                $table->string('full_name')->nullable();
                $table->string('phone_number')->nullable();
                $table->string('email')->nullable();
                
                $table->json('raw_payload')->nullable();
                $table->json('extracted_data')->nullable();
                
                $table->unsignedBigInteger('crm_lead_id')->nullable()->index();
                $table->enum('status', ['pending', 'processed', 'failed', 'duplicate'])->default('pending')->index();
                $table->text('error_message')->nullable();
                $table->timestamp('lead_created_time')->nullable();
                
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meta_lead_logs');
    }
};
