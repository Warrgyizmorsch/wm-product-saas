<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('assets')) {
            return;
        }

        Schema::table('assets', function (Blueprint $table) {
            if (!Schema::hasColumn('assets', 'description')) {
                $table->text('description')->nullable()->after('name');
            }

            if (!Schema::hasColumn('assets', 'vendor_id')) {
                $table->foreignId('vendor_id')->nullable()->after('asset_category_id')
                      ->constrained('vendors')->nullOnDelete();
            }

            if (!Schema::hasColumn('assets', 'purchase_order_id')) {
                $table->foreignId('purchase_order_id')->nullable()->after('vendor_id')
                      ->constrained('purchase_orders')->nullOnDelete();
            }

            if (!Schema::hasColumn('assets', 'purchase_order_item_id')) {
                $table->foreignId('purchase_order_item_id')->nullable()->after('purchase_order_id')
                      ->constrained('purchase_order_items')->nullOnDelete();
            }

            if (!Schema::hasColumn('assets', 'invoice_number')) {
                $table->string('invoice_number')->nullable()->after('purchase_order_item_id');
            }

            if (!Schema::hasColumn('assets', 'capitalization_date')) {
                $table->date('capitalization_date')->nullable()->after('purchase_date');
            }

            if (!Schema::hasColumn('assets', 'commissioning_date')) {
                $table->date('commissioning_date')->nullable()->after('capitalization_date');
            }

            if (!Schema::hasColumn('assets', 'acquisition_cost')) {
                $table->decimal('acquisition_cost', 14, 2)->nullable()->after('purchase_cost');
            }

            if (!Schema::hasColumn('assets', 'directly_attributable_cost')) {
                $table->decimal('directly_attributable_cost', 14, 2)->default(0)->after('acquisition_cost');
            }

            if (!Schema::hasColumn('assets', 'capitalization_cost')) {
                $table->decimal('capitalization_cost', 14, 2)->nullable()->after('directly_attributable_cost');
            }

            if (!Schema::hasColumn('assets', 'recoverable_tax')) {
                $table->decimal('recoverable_tax', 14, 2)->default(0)->after('capitalization_cost');
            }

            if (!Schema::hasColumn('assets', 'non_recoverable_tax')) {
                $table->decimal('non_recoverable_tax', 14, 2)->default(0)->after('recoverable_tax');
            }

            if (!Schema::hasColumn('assets', 'residual_value')) {
                $table->decimal('residual_value', 14, 2)->default(0)->after('non_recoverable_tax');
            }

            if (!Schema::hasColumn('assets', 'useful_life_months')) {
                $table->unsignedInteger('useful_life_months')->nullable()->after('residual_value');
            }

            if (!Schema::hasColumn('assets', 'depreciation_method')) {
                $table->string('depreciation_method')->nullable()->after('useful_life_months');
            }

            if (!Schema::hasColumn('assets', 'depreciation_start_date')) {
                $table->date('depreciation_start_date')->nullable()->after('depreciation_method');
            }

            if (!Schema::hasColumn('assets', 'accumulated_depreciation')) {
                $table->decimal('accumulated_depreciation', 14, 2)->default(0)->after('depreciation_start_date');
            }

            if (!Schema::hasColumn('assets', 'book_value')) {
                $table->decimal('book_value', 14, 2)->nullable()->after('accumulated_depreciation');
            }

            if (!Schema::hasColumn('assets', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('company_id')
                      ->constrained('branches')->nullOnDelete();
            }

            if (!Schema::hasColumn('assets', 'department_id')) {
                $table->foreignId('department_id')->nullable()->after('branch_id')
                      ->constrained('departments')->nullOnDelete();
            }

            if (!Schema::hasColumn('assets', 'location_label')) {
                $table->string('location_label')->nullable()->after('department_id');
            }

            if (!Schema::hasColumn('assets', 'warranty_start_date')) {
                $table->date('warranty_start_date')->nullable()->after('expected_return_date');
            }

            if (!Schema::hasColumn('assets', 'warranty_end_date')) {
                $table->date('warranty_end_date')->nullable()->after('warranty_start_date');
            }

            if (!Schema::hasColumn('assets', 'insurance_start_date')) {
                $table->date('insurance_start_date')->nullable()->after('warranty_end_date');
            }

            if (!Schema::hasColumn('assets', 'insurance_end_date')) {
                $table->date('insurance_end_date')->nullable()->after('insurance_start_date');
            }

            if (!Schema::hasColumn('assets', 'barcode')) {
                $table->string('barcode')->nullable()->after('insurance_end_date')->index();
            }

            if (!Schema::hasColumn('assets', 'qr_code')) {
                $table->string('qr_code')->nullable()->after('barcode');
            }

            if (!Schema::hasColumn('assets', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('qr_code')
                      ->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('assets', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')
                      ->constrained('users')->nullOnDelete();
            }
        });

        // Widen status from the native enum(available/allocated/maintenance/scrapped)
        // to a plain VARCHAR so the fuller Fixed Asset status set fits, mirroring the
        // precedent already set for asset_requests.status in
        // 2026_08_31_120200_add_grn_link_to_hrms_assets_tables.php's sibling migration
        // (2026_07_21_172000 for asset_requests). Existing 4 values stay valid.
        if (Schema::hasColumn('assets', 'status') && DB::getDriverName() === 'mysql') {
            try {
                DB::statement("ALTER TABLE assets MODIFY COLUMN status VARCHAR(50) DEFAULT 'available'");
            } catch (\Throwable $e) {
                // Ignore if already altered or driver unsupported
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('assets')) {
            return;
        }

        Schema::table('assets', function (Blueprint $table) {
            foreach ([
                'vendor_id', 'purchase_order_id', 'purchase_order_item_id',
                'branch_id', 'department_id', 'created_by', 'updated_by',
            ] as $column) {
                if (Schema::hasColumn('assets', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach ([
                'description', 'invoice_number', 'capitalization_date', 'commissioning_date',
                'acquisition_cost', 'directly_attributable_cost', 'capitalization_cost',
                'recoverable_tax', 'non_recoverable_tax', 'residual_value', 'useful_life_months',
                'depreciation_method', 'depreciation_start_date', 'accumulated_depreciation',
                'book_value', 'location_label', 'warranty_start_date', 'warranty_end_date',
                'insurance_start_date', 'insurance_end_date', 'barcode', 'qr_code',
            ] as $column) {
                if (Schema::hasColumn('assets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
