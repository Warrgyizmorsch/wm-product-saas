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
        if (Schema::hasTable('production_shifts')) {
            // 1. Create a temporary index on tenant_id to satisfy the foreign key constraint
            Schema::table('production_shifts', function (Blueprint $table) {
                $table->index('tenant_id', 'production_shifts_tenant_id_temp_index');
            });

            // 2. Safely drop the old unique constraint, add company_id, and establish the new unique constraint
            Schema::table('production_shifts', function (Blueprint $table) {
                try {
                    $table->dropUnique('production_shifts_tenant_id_code_unique');
                } catch (\Exception $e) {
                    // Ignore if constraint name differs or is not found
                }

                if (!Schema::hasColumn('production_shifts', 'company_id')) {
                    $table->unsignedBigInteger('company_id')->nullable()->after('tenant_id');
                    $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
                }

                $table->unique(['tenant_id', 'company_id', 'code'], 'production_shifts_tenant_company_code_unique');
            });

            // 3. Drop the temporary index as it's now covered by the prefix of the new unique index
            Schema::table('production_shifts', function (Blueprint $table) {
                try {
                    $table->dropIndex('production_shifts_tenant_id_temp_index');
                } catch (\Exception $e) {}
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('production_shifts')) {
            // 1. Create a temporary index on tenant_id
            Schema::table('production_shifts', function (Blueprint $table) {
                $table->index('tenant_id', 'production_shifts_tenant_id_temp_index');
            });

            // 2. Drop the new unique constraint, drop foreign and column, and restore old unique key
            Schema::table('production_shifts', function (Blueprint $table) {
                try {
                    $table->dropUnique('production_shifts_tenant_company_code_unique');
                } catch (\Exception $e) {}

                try {
                    $table->dropForeign(['company_id']);
                } catch (\Exception $e) {}

                if (Schema::hasColumn('production_shifts', 'company_id')) {
                    $table->dropColumn('company_id');
                }

                $table->unique(['tenant_id', 'code'], 'production_shifts_tenant_id_code_unique');
            });

            // 3. Drop the temporary index
            Schema::table('production_shifts', function (Blueprint $table) {
                try {
                    $table->dropIndex('production_shifts_tenant_id_temp_index');
                } catch (\Exception $e) {}
            });
        }
    }
};
