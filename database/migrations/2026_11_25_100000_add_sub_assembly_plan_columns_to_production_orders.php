<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UR-10 / UR-12 (M-22 .. M-24) — sub-assemblies are made per consuming operation, and the planner remembers how.
 *
 * production_order_operations
 *   plan_key         where the operation sits in the plan tree ("r12" = an operation of the order's own routing, "r12>7/55" = routing
 *                    operation 55 of child BOM 7 that makes the sub-assembly for operation r12). Null on orders created before this change.
 *   execution_order  1, 2, 3 … the order the operations are executed in (sub-assembly operations first, then the operation that needs them).
 * production_orders
 *   sub_assembly_plan  JSON: the stock the plan was made with, what each consuming operation needs / takes from stock / has to make,
 *                      and the "produce all at one level" choices the user made.
 *
 * No new status column is needed for the new order status "locked" (production_orders.status is a plain string).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_order_operations')) {
            Schema::table('production_order_operations', function (Blueprint $table) {
                if (!Schema::hasColumn('production_order_operations', 'plan_key')) {
                    $table->string('plan_key', 191)->nullable();
                    $table->index(['production_order_id', 'plan_key'], 'po_ops_order_plan_key_idx');
                }
                if (!Schema::hasColumn('production_order_operations', 'execution_order')) {
                    $table->unsignedInteger('execution_order')->nullable();
                }
            });
        }

        if (Schema::hasTable('production_orders') && !Schema::hasColumn('production_orders', 'sub_assembly_plan')) {
            Schema::table('production_orders', function (Blueprint $table) {
                $table->json('sub_assembly_plan')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_order_operations')) {
            Schema::table('production_order_operations', function (Blueprint $table) {
                if (Schema::hasColumn('production_order_operations', 'plan_key')) {
                    $table->dropIndex('po_ops_order_plan_key_idx');
                    $table->dropColumn('plan_key');
                }
                if (Schema::hasColumn('production_order_operations', 'execution_order')) {
                    $table->dropColumn('execution_order');
                }
            });
        }

        if (Schema::hasTable('production_orders') && Schema::hasColumn('production_orders', 'sub_assembly_plan')) {
            Schema::table('production_orders', function (Blueprint $table) {
                $table->dropColumn('sub_assembly_plan');
            });
        }
    }
};
