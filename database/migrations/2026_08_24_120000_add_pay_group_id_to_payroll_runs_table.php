<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tablePrefix = Schema::getConnection()->getTablePrefix();
        $rawTableName = 'payroll_runs';

        // Retrieve current schema states
        $indexes = Schema::getIndexes($rawTableName);
        $indexNames = array_column($indexes, 'name');

        $foreignKeys = Schema::getForeignKeys($rawTableName);
        $foreignKeyNames = array_column($foreignKeys, 'name');

        // 1. Create a temporary index on company_id if it does not already exist
        $tempIndexName = $rawTableName . '_company_id_index';
        $hasTempIndex = in_array($tempIndexName, $indexNames) || in_array($tablePrefix . $tempIndexName, $indexNames);

        if (!$hasTempIndex) {
            Schema::table($rawTableName, function (Blueprint $table) {
                $table->index('company_id');
            });
        }

        // 2. Drop the old unique constraint if it still exists
        $oldUniqueName = $rawTableName . '_company_id_payroll_month_unique';
        $hasOldUnique = in_array($oldUniqueName, $indexNames) || in_array($tablePrefix . $oldUniqueName, $indexNames);

        if ($hasOldUnique) {
            Schema::table($rawTableName, function (Blueprint $table) use ($oldUniqueName) {
                $table->dropUnique($oldUniqueName);
            });
        }

        // 3. Add column if it does not exist
        if (!Schema::hasColumn($rawTableName, 'pay_group_id')) {
            Schema::table($rawTableName, function (Blueprint $table) {
                $table->unsignedBigInteger('pay_group_id')->nullable()->after('company_id');
            });
        }

        // 4. Create the new composite unique constraint if it does not exist
        $newUniqueName = $rawTableName . '_company_id_payroll_month_pay_group_id_unique';
        $hasNewUnique = in_array($newUniqueName, $indexNames) || in_array($tablePrefix . $newUniqueName, $indexNames);

        if (!$hasNewUnique) {
            Schema::table($rawTableName, function (Blueprint $table) {
                $table->unique(['company_id', 'payroll_month', 'pay_group_id']);
            });
        }

        // 5. Add pay_group_id foreign key if it does not exist
        $fkName = $rawTableName . '_pay_group_id_foreign';
        $hasFk = in_array($fkName, $foreignKeyNames) || in_array($tablePrefix . $fkName, $foreignKeyNames);

        if (!$hasFk) {
            Schema::table($rawTableName, function (Blueprint $table) {
                $table->foreign('pay_group_id')->references('id')->on('pay_groups')->nullOnDelete();
            });
        }

        // 6. Clean up: Drop the temporary index if it exists
        $freshIndexes = Schema::getIndexes($rawTableName);
        $freshIndexNames = array_column($freshIndexes, 'name');
        $hasTempIndexFresh = in_array($tempIndexName, $freshIndexNames) || in_array($tablePrefix . $tempIndexName, $freshIndexNames);

        if ($hasTempIndexFresh) {
            Schema::table($rawTableName, function (Blueprint $table) use ($tempIndexName) {
                $table->dropIndex($tempIndexName);
            });
        }
    }

    public function down(): void
    {
        $tablePrefix = Schema::getConnection()->getTablePrefix();
        $rawTableName = 'payroll_runs';

        $indexes = Schema::getIndexes($rawTableName);
        $indexNames = array_column($indexes, 'name');

        $foreignKeys = Schema::getForeignKeys($rawTableName);
        $foreignKeyNames = array_column($foreignKeys, 'name');

        // 1. Create temporary index on company_id if not present
        $tempIndexName = $rawTableName . '_company_id_index';
        $hasTempIndex = in_array($tempIndexName, $indexNames) || in_array($tablePrefix . $tempIndexName, $indexNames);
        if (!$hasTempIndex) {
            Schema::table($rawTableName, function (Blueprint $table) {
                $table->index('company_id');
            });
        }

        // 2. Drop pay_group_id foreign key if present
        $fkName = $rawTableName . '_pay_group_id_foreign';
        $hasFk = in_array($fkName, $foreignKeyNames) || in_array($tablePrefix . $fkName, $foreignKeyNames);
        if ($hasFk) {
            Schema::table($rawTableName, function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        }

        // 3. Drop composite unique index if present
        $newUniqueName = $rawTableName . '_company_id_payroll_month_pay_group_id_unique';
        $hasNewUnique = in_array($newUniqueName, $indexNames) || in_array($tablePrefix . $newUniqueName, $indexNames);
        if ($hasNewUnique) {
            Schema::table($rawTableName, function (Blueprint $table) use ($newUniqueName) {
                $table->dropUnique($newUniqueName);
            });
        }

        // 4. Remove pay_group_id and restore the original unique constraint
        if (Schema::hasColumn($rawTableName, 'pay_group_id')) {
            Schema::table($rawTableName, function (Blueprint $table) {
                $table->dropColumn('pay_group_id');
            });
        }

        $oldUniqueName = $rawTableName . '_company_id_payroll_month_unique';
        $freshIndexes = Schema::getIndexes($rawTableName);
        $freshIndexNames = array_column($freshIndexes, 'name');
        $hasOldUnique = in_array($oldUniqueName, $freshIndexNames) || in_array($tablePrefix . $oldUniqueName, $freshIndexNames);

        if (!$hasOldUnique) {
            Schema::table($rawTableName, function (Blueprint $table) {
                $table->unique(['company_id', 'payroll_month']);
            });
        }

        // 5. Clean up the temporary index
        $freshIndexesAfter = Schema::getIndexes($rawTableName);
        $freshIndexNamesAfter = array_column($freshIndexesAfter, 'name');
        $hasTempIndexAfter = in_array($tempIndexName, $freshIndexNamesAfter) || in_array($tablePrefix . $tempIndexName, $freshIndexNamesAfter);

        if ($hasTempIndexAfter) {
            Schema::table($rawTableName, function (Blueprint $table) use ($tempIndexName) {
                $table->dropIndex($tempIndexName);
            });
        }
    }
};
