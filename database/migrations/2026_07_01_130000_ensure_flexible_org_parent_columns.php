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
        Schema::table('branches', function (Blueprint $table) {
            if ($this->foreignKeyExists('branches', 'branches_business_unit_id_foreign')) {
                $table->dropForeign(['business_unit_id']);
            }

            $table->unsignedBigInteger('business_unit_id')->nullable()->change();
            $table->foreign('business_unit_id')->references('id')->on('business_units')->nullOnDelete();

            if (!Schema::hasColumn('branches', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('business_unit_id');
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            }
        });

        Schema::table('departments', function (Blueprint $table) {
            if ($this->foreignKeyExists('departments', 'departments_branch_id_foreign')) {
                $table->dropForeign(['branch_id']);
            }

            if ($this->indexExists('departments', 'departments_branch_id_code_unique')) {
                $table->dropUnique('departments_branch_id_code_unique');
            }

            $table->unsignedBigInteger('branch_id')->nullable()->change();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();

            if (!Schema::hasColumn('departments', 'company_id')) {
                $table->unsignedBigInteger('company_id')->nullable()->after('branch_id');
                $table->foreign('company_id')->references('id')->on('companies')->nullOnDelete();
            }

            if (!Schema::hasColumn('departments', 'business_unit_id')) {
                $table->unsignedBigInteger('business_unit_id')->nullable()->after('company_id');
                $table->foreign('business_unit_id')->references('id')->on('business_units')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            if (Schema::hasColumn('departments', 'business_unit_id')) {
                $table->dropForeign(['business_unit_id']);
                $table->dropColumn('business_unit_id');
            }

            if (Schema::hasColumn('departments', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });

        Schema::table('branches', function (Blueprint $table) {
            if (Schema::hasColumn('branches', 'company_id')) {
                $table->dropForeign(['company_id']);
                $table->dropColumn('company_id');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))->contains(fn ($existingIndex) => $existingIndex['name'] === $index);
    }

    private function foreignKeyExists(string $table, string $foreignKey): bool
    {
        return collect(Schema::getForeignKeys($table))->contains(fn ($existingKey) => $existingKey['name'] === $foreignKey);
    }
};
