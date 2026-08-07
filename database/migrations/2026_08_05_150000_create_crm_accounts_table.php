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
        Schema::create('crm_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
            $table->unsignedBigInteger('customer_id')->nullable()->index();
            $table->string('account_number')->nullable()->index();
            $table->string('name');
            $table->string('gstin')->nullable()->index();
            $table->string('email')->nullable();
            $table->string('phone')->nullable()->index();
            $table->string('website')->nullable();
            $table->string('industry_type')->nullable();
            $table->decimal('credit_limit', 15, 2)->default(0.00);
            $table->string('street')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('zip_code')->nullable();
            $table->string('status')->default('active');
            $table->unsignedBigInteger('owner_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_accounts');
    }
};
