<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('landed_cost_expenses')) {
            Schema::table('landed_cost_expenses', function (Blueprint $table) {
                if (!Schema::hasColumn('landed_cost_expenses', 'tax_rate')) {
                    $table->decimal('tax_rate', 8, 2)->default(0.00)->after('amount');
                }
                if (!Schema::hasColumn('landed_cost_expenses', 'gst_type')) {
                    $table->string('gst_type', 20)->default('cgst_sgst')->after('tax_rate'); // cgst_sgst, igst, rcm
                }
                if (!Schema::hasColumn('landed_cost_expenses', 'is_rcm')) {
                    $table->boolean('is_rcm')->default(false)->after('gst_type');
                }
                if (!Schema::hasColumn('landed_cost_expenses', 'tax_amount')) {
                    $table->decimal('tax_amount', 15, 4)->default(0.0000)->after('is_rcm');
                }
                if (!Schema::hasColumn('landed_cost_expenses', 'total_with_tax')) {
                    $table->decimal('total_with_tax', 15, 4)->default(0.0000)->after('tax_amount');
                }
                if (!Schema::hasColumn('landed_cost_expenses', 'vendor_bill_id')) {
                    $table->unsignedBigInteger('vendor_bill_id')->nullable()->after('total_with_tax');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('landed_cost_expenses')) {
            Schema::table('landed_cost_expenses', function (Blueprint $table) {
                $table->dropColumn(['tax_rate', 'gst_type', 'is_rcm', 'tax_amount', 'total_with_tax', 'vendor_bill_id']);
            });
        }
    }
};
