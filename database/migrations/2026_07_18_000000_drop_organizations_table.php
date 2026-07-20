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
        // 1. Drop foreign keys and columns in connected tables
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }

        if (Schema::hasTable('salary_components')) {
            Schema::table('salary_components', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }

        if (Schema::hasTable('pay_groups')) {
            Schema::table('pay_groups', function (Blueprint $table) {
                $table->dropForeign(['organization_id']);
                $table->dropColumn('organization_id');
            });
        }

        // 2. Drop the organizations table
        Schema::dropIfExists('organizations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Recreate organizations table
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('logo')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('website')->nullable();
            $table->string('subscription_plan');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
        });

        // 2. Restore columns and foreign keys in connected tables
        if (Schema::hasTable('companies')) {
            Schema::table('companies', function (Blueprint $table) {
                $table->foreignId('organization_id')
                      ->after('tenant_id')
                      ->constrained('organizations')
                      ->cascadeOnUpdate()
                      ->cascadeOnDelete();
            });
        }

        if (Schema::hasTable('salary_components')) {
            Schema::table('salary_components', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable()->after('tenant_id');
                $table->foreign('organization_id')->references('id')->on('organizations')->nullOnDelete();
            });
        }

        if (Schema::hasTable('pay_groups')) {
            Schema::table('pay_groups', function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->default(1)->after('tenant_id');
                $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            });
        }
    }
};
