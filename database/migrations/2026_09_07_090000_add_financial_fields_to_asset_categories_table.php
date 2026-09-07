<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('asset_categories')) {
            return;
        }

        Schema::table('asset_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('asset_categories', 'accumulated_depreciation_account_id')) {
                $table->foreignId('accumulated_depreciation_account_id')
                      ->nullable()
                      ->after('fixed_asset_account_id')
                      ->constrained('chart_of_accounts')
                      ->nullOnDelete();
            }

            if (!Schema::hasColumn('asset_categories', 'depreciation_expense_account_id')) {
                $table->foreignId('depreciation_expense_account_id')
                      ->nullable()
                      ->after('accumulated_depreciation_account_id')
                      ->constrained('chart_of_accounts')
                      ->nullOnDelete();
            }

            if (!Schema::hasColumn('asset_categories', 'gain_on_disposal_account_id')) {
                $table->foreignId('gain_on_disposal_account_id')
                      ->nullable()
                      ->after('depreciation_expense_account_id')
                      ->constrained('chart_of_accounts')
                      ->nullOnDelete();
            }

            if (!Schema::hasColumn('asset_categories', 'loss_on_disposal_account_id')) {
                $table->foreignId('loss_on_disposal_account_id')
                      ->nullable()
                      ->after('gain_on_disposal_account_id')
                      ->constrained('chart_of_accounts')
                      ->nullOnDelete();
            }

            if (!Schema::hasColumn('asset_categories', 'default_depreciation_method')) {
                $table->string('default_depreciation_method')->nullable()->after('loss_on_disposal_account_id');
            }

            if (!Schema::hasColumn('asset_categories', 'default_useful_life_months')) {
                $table->unsignedInteger('default_useful_life_months')->nullable()->after('default_depreciation_method');
            }

            if (!Schema::hasColumn('asset_categories', 'default_residual_value_percent')) {
                $table->decimal('default_residual_value_percent', 5, 2)->nullable()->after('default_useful_life_months');
            }

            if (!Schema::hasColumn('asset_categories', 'capitalization_threshold')) {
                $table->decimal('capitalization_threshold', 14, 2)->nullable()->after('default_residual_value_percent');
            }

            if (!Schema::hasColumn('asset_categories', 'status')) {
                $table->string('status')->default('active')->after('capitalization_threshold');
            }
        });

        // The pre-existing fixed_asset_account_id column was added as a raw
        // unsignedBigInteger with no FK constraint (2026_08_31_120200). Add the
        // constraint now that we know the column is actually populated with
        // chart_of_accounts ids in practice — skipped if it would fail due to
        // orphaned data, since this is a defensive hardening addition, not a
        // functional requirement of this migration.
        if (Schema::hasColumn('asset_categories', 'fixed_asset_account_id') && DB::getDriverName() === 'mysql') {
            $hasConstraint = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', 'asset_categories')
                ->where('COLUMN_NAME', 'fixed_asset_account_id')
                ->whereNotNull('REFERENCED_TABLE_NAME')
                ->exists();

            if (!$hasConstraint) {
                try {
                    Schema::table('asset_categories', function (Blueprint $table) {
                        $table->foreign('fixed_asset_account_id')
                              ->references('id')->on('chart_of_accounts')
                              ->nullOnDelete();
                    });
                } catch (\Throwable $e) {
                    // Orphaned fixed_asset_account_id values would block this constraint;
                    // leave the column unconstrained rather than fail the migration.
                }
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('asset_categories')) {
            return;
        }

        Schema::table('asset_categories', function (Blueprint $table) {
            foreach ([
                'accumulated_depreciation_account_id',
                'depreciation_expense_account_id',
                'gain_on_disposal_account_id',
                'loss_on_disposal_account_id',
            ] as $column) {
                if (Schema::hasColumn('asset_categories', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach ([
                'default_depreciation_method',
                'default_useful_life_months',
                'default_residual_value_percent',
                'capitalization_threshold',
                'status',
            ] as $column) {
                if (Schema::hasColumn('asset_categories', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
