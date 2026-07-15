<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * child table => [parent table, foreign key column on the child].
     * All parent tables already carry tenant_id (they extend BaseModel);
     * these child "line item" tables never got their own tenant_id column.
     */
    private array $tables = [
        'quotation_items' => ['quotations', 'quotation_id'],
        'purchase_requisition_items' => ['purchase_requisitions', 'purchase_requisition_id'],
        'delivery_order_items' => ['delivery_orders', 'delivery_order_id'],
        'sales_return_items' => ['sales_returns', 'sales_return_id'],
        'sales_order_items' => ['sales_orders', 'sales_order_id'],
        'invoice_items' => ['invoices', 'invoice_id'],
        'dispatch_order_items' => ['dispatch_orders', 'dispatch_order_id'],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => [$parentTable, $foreignKey]) {
            if (Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('tenant_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });

            // Correlated subquery backfill (portable across MySQL/SQLite, unlike a join-update).
            DB::statement(
                "UPDATE {$table} SET tenant_id = (SELECT tenant_id FROM {$parentTable} WHERE {$parentTable}.id = {$table}.{$foreignKey}) WHERE tenant_id IS NULL"
            );
        }
    }

    public function down(): void
    {
        foreach (array_reverse(array_keys($this->tables)) as $table) {
            if (! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropForeign(['tenant_id']);
                $blueprint->dropColumn('tenant_id');
            });
        }
    }
};
