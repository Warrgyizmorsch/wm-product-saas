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
        Schema::create('hrms_notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->default(1)->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('business_unit_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();
            $table->string('type', 100)->default('general');
            $table->string('title');
            $table->text('message');
            $table->string('action_url', 500)->nullable();
            $table->string('icon_class', 100)->default('feather-bell');
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrms_notifications');
    }
};
