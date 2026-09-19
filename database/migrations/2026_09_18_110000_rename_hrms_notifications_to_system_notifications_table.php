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
        if (Schema::hasTable('hrms_notifications') && !Schema::hasTable('notifications')) {
            Schema::rename('hrms_notifications', 'notifications');
        } elseif (Schema::hasTable('system_notifications') && !Schema::hasTable('notifications')) {
            Schema::rename('system_notifications', 'notifications');
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('business_unit_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('user_id')->index();
                $table->unsignedBigInteger('employee_id')->nullable()->index();
                $table->string('module', 50)->default('hrms')->index();
                $table->string('type', 100)->default('general');
                $table->string('title');
                $table->text('message');
                $table->string('action_url', 500)->nullable();
                $table->string('icon_class', 100)->default('feather-bell');
                $table->json('data')->nullable();
                $table->timestamp('read_at')->nullable()->index();
                $table->timestamps();
            });
        } elseif (Schema::hasTable('notifications')) {
            Schema::table('notifications', function (Blueprint $table) {
                if (!Schema::hasColumn('notifications', 'module')) {
                    $table->string('module', 50)->default('hrms')->after('employee_id')->index();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('notifications')) {
            Schema::dropIfExists('notifications');
        }
    }
};
