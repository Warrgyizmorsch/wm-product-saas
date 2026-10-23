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
        if (!Schema::hasTable('twilio_configurations')) {
            Schema::create('twilio_configurations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();

                $table->string('name')->default('Primary Twilio Account');
                $table->string('account_sid')->nullable();
                $table->text('auth_token')->nullable();
                $table->string('phone_number')->nullable(); // e.g. +1234567890
                $table->string('twiml_app_sid')->nullable();

                $table->boolean('record_calls')->default(true);
                $table->boolean('auto_summarize_ai')->default(true);
                $table->text('gemini_api_key')->nullable();

                $table->unsignedBigInteger('default_lead_owner_id')->nullable()->index();
                $table->string('default_source')->default('Twilio Call');
                $table->string('default_priority')->default('Medium');

                $table->boolean('is_active')->default(true);
                $table->json('extra_metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('twilio_configurations');
    }
};
