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
        if (!Schema::hasTable('meta_configurations')) {
            Schema::create('meta_configurations', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();

                $table->string('name')->default('Primary Meta Account');
                $table->string('app_id')->nullable();
                $table->string('app_secret')->nullable();
                $table->text('access_token')->nullable(); // System User / Page / User Access Token
                $table->string('ad_account_id')->nullable(); // e.g. act_1234567890
                $table->string('page_id')->nullable(); // Facebook Page ID
                $table->string('pixel_id')->nullable(); // Meta Pixel / Dataset ID
                $table->string('verify_token')->nullable(); // Custom webhook verify token

                $table->unsignedBigInteger('default_lead_owner_id')->nullable()->index();
                $table->string('default_source')->default('Meta Ads');
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
        Schema::dropIfExists('meta_configurations');
    }
};
