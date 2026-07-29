<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // ── invoices table ─────────────────────────────────────────────────────
        Schema::table('invoices', function (Blueprint $table) {
            // Link to customer directly (for quick lookups / policy checks)
            if (!Schema::hasColumn('invoices', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('tenant_id');
            }
            // material_requirement_id replaces the old delivery_order_id
            if (!Schema::hasColumn('invoices', 'material_requirement_id')) {
                $table->unsignedBigInteger('material_requirement_id')->nullable()->after('sales_order_id');
            }
            // Rename old aggregate columns → new names used by controller/model
            if (Schema::hasColumn('invoices', 'tax_total') && !Schema::hasColumn('invoices', 'tax_amount')) {
                $table->renameColumn('tax_total', 'tax_amount');
            }
            if (Schema::hasColumn('invoices', 'discount') && !Schema::hasColumn('invoices', 'discount_amount')) {
                $table->renameColumn('discount', 'discount_amount');
            }
            if (Schema::hasColumn('invoices', 'grand_total') && !Schema::hasColumn('invoices', 'total_amount')) {
                $table->renameColumn('grand_total', 'total_amount');
            }
            // Payment tracking columns
            if (!Schema::hasColumn('invoices', 'amount_paid')) {
                $table->decimal('amount_paid', 15, 2)->default(0.00)->after('total_amount');
            }
            if (!Schema::hasColumn('invoices', 'balance_due')) {
                $table->decimal('balance_due', 15, 2)->default(0.00)->after('amount_paid');
            }
        });

        // ── invoice_items table ────────────────────────────────────────────────
        Schema::table('invoice_items', function (Blueprint $table) {
            // Rename old FK to new name
            if (Schema::hasColumn('invoice_items', 'delivery_order_item_id') && !Schema::hasColumn('invoice_items', 'material_requirement_item_id')) {
                $table->renameColumn('delivery_order_item_id', 'material_requirement_item_id');
            }
            if (!Schema::hasColumn('invoice_items', 'material_requirement_item_id')) {
                $table->unsignedBigInteger('material_requirement_item_id')->nullable()->after('sales_order_item_id');
            }
            // Make warehouse nullable (not every invoice line has a warehouse)
            if (Schema::hasColumn('invoice_items', 'warehouse_id')) {
                $table->unsignedBigInteger('warehouse_id')->nullable()->change();
            }
            // item name / description
            if (!Schema::hasColumn('invoice_items', 'item_name')) {
                $table->string('item_name')->nullable()->after('warehouse_id');
            }
            if (!Schema::hasColumn('invoice_items', 'description')) {
                $table->text('description')->nullable()->after('item_name');
            }
            // tax & total columns
            if (!Schema::hasColumn('invoice_items', 'tax_amount')) {
                $table->decimal('tax_amount', 15, 2)->default(0.00)->after('tax_rate');
            }
            if (!Schema::hasColumn('invoice_items', 'total_amount')) {
                $table->decimal('total_amount', 15, 2)->default(0.00)->after('subtotal');
            }
            // tenant_id (needed by BelongsToTenant trait)
            if (!Schema::hasColumn('invoice_items', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('invoices', 'customer_id')          ? 'customer_id'          : null,
                Schema::hasColumn('invoices', 'material_requirement_id') ? 'material_requirement_id' : null,
                Schema::hasColumn('invoices', 'amount_paid')          ? 'amount_paid'          : null,
                Schema::hasColumn('invoices', 'balance_due')          ? 'balance_due'          : null,
            ]));
        });

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('invoice_items', 'item_name')    ? 'item_name'    : null,
                Schema::hasColumn('invoice_items', 'description')  ? 'description'  : null,
                Schema::hasColumn('invoice_items', 'tax_amount')   ? 'tax_amount'   : null,
                Schema::hasColumn('invoice_items', 'total_amount') ? 'total_amount' : null,
                Schema::hasColumn('invoice_items', 'tenant_id')    ? 'tenant_id'    : null,
            ]));
        });
    }
};
