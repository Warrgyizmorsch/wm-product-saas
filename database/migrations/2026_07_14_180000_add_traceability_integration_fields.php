<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── 1. Production Scan Logs — add audit & status fields ────────────────
        Schema::table('production_scan_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('production_scan_logs', 'raw_code')) {
                $table->string('raw_code')->nullable()->after('entity_id')
                    ->comment('The literal string presented to the scanner (business identifier)');
            }
            if (! Schema::hasColumn('production_scan_logs', 'status')) {
                $table->string('status')->default('success')->after('raw_code')
                    ->comment('success | failed');
                $table->index('status', 'psl_status_idx');
            }
            if (! Schema::hasColumn('production_scan_logs', 'action_taken')) {
                $table->string('action_taken')->nullable()->after('status')
                    ->comment('e.g. issue_material, receive_fg, log_scrap, view');
            }
            if (! Schema::hasColumn('production_scan_logs', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('action_taken');
            }
        });

        // ── 2. Production Order Scraps — idempotency guard ────────────────────
        // stock_transaction_id is set once when the stock outflow is posted.
        // If not null, the posting has already occurred — prevents double-posting.
        Schema::table('production_order_scraps', function (Blueprint $table) {
            if (! Schema::hasColumn('production_order_scraps', 'stock_transaction_id')) {
                $table->unsignedBigInteger('stock_transaction_id')->nullable()->after('recorded_at');
                $table->foreign('stock_transaction_id', 'pos_stx_fk')
                    ->references('id')
                    ->on('stock_transactions')
                    ->nullOnDelete(); // preserves scrap record if stock transaction pruned
            }
        });

        // ── 3. Production Order Receipts — inventory linkage ──────────────────
        // inventory_batch_id: the Inventory::Batch record created by StockService::recordInflow().
        // nullOnDelete: deleting the inventory batch must NOT delete the production receipt.
        // serial_numbers: immutable JSON snapshot of serial strings at receipt time.
        //   The authoritative serial ledger remains the inventory serial_numbers table.
        Schema::table('production_order_receipts', function (Blueprint $table) {
            if (! Schema::hasColumn('production_order_receipts', 'inventory_batch_id')) {
                $table->unsignedBigInteger('inventory_batch_id')->nullable()->after('warehouse_id');
                $table->foreign('inventory_batch_id', 'por_inv_batch_fk')
                    ->references('id')
                    ->on('batches')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('production_order_receipts', 'serial_numbers')) {
                $table->json('serial_numbers')->nullable()->after('inventory_batch_id')
                    ->comment('Immutable snapshot of serial strings at receipt time. Authoritative ledger is inventory serial_numbers table.');
            }
        });

        // ── 4. Production Order Issues — inventory batch linkage ──────────────
        // Records which specific inventory batch was consumed during material issue.
        // nullOnDelete: deleting the batch row must NOT destroy issue history.
        Schema::table('production_order_issues', function (Blueprint $table) {
            if (! Schema::hasColumn('production_order_issues', 'inventory_batch_id')) {
                $table->unsignedBigInteger('inventory_batch_id')->nullable()->after('warehouse_id');
                $table->foreign('inventory_batch_id', 'poi_inv_batch_fk')
                    ->references('id')
                    ->on('batches')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_order_issues', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_issues', 'inventory_batch_id')) {
                $table->dropForeign('poi_inv_batch_fk');
                $table->dropColumn('inventory_batch_id');
            }
        });

        Schema::table('production_order_receipts', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_receipts', 'serial_numbers')) {
                $table->dropColumn('serial_numbers');
            }
            if (Schema::hasColumn('production_order_receipts', 'inventory_batch_id')) {
                $table->dropForeign('por_inv_batch_fk');
                $table->dropColumn('inventory_batch_id');
            }
        });

        Schema::table('production_order_scraps', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_scraps', 'stock_transaction_id')) {
                $table->dropForeign('pos_stx_fk');
                $table->dropColumn('stock_transaction_id');
            }
        });

        Schema::table('production_scan_logs', function (Blueprint $table) {
            if (Schema::hasColumn('production_scan_logs', 'failure_reason')) {
                $table->dropColumn('failure_reason');
            }
            if (Schema::hasColumn('production_scan_logs', 'action_taken')) {
                $table->dropColumn('action_taken');
            }
            if (Schema::hasColumn('production_scan_logs', 'status')) {
                $table->dropIndex('psl_status_idx');
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('production_scan_logs', 'raw_code')) {
                $table->dropColumn('raw_code');
            }
        });
    }
};
