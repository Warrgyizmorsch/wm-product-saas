<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Sync branch_id and company_id for any requisition slips from their parent orders
        if (Schema::hasTable('production_requisition_slips') && Schema::hasTable('production_orders')) {
            $slips = DB::table('production_requisition_slips')
                ->whereNull('branch_id')
                ->get();

            foreach ($slips as $slip) {
                $order = DB::table('production_orders')->where('id', $slip->production_order_id)->first();
                if ($order && $order->branch_id) {
                    DB::table('production_requisition_slips')->where('id', $slip->id)->update([
                        'branch_id'  => $order->branch_id,
                        'company_id' => $order->company_id,
                    ]);
                }
            }
        }

        // 2. Drop tenant-global unique constraints on operational tables
        if (Schema::hasTable('production_orders')) {
            Schema::table('production_orders', function (Blueprint $table) {
                // Drop old unique constraint on (tenant_id, order_number)
                try {
                    $table->dropUnique('production_orders_tenant_number_unique');
                } catch (\Throwable) {
                    // Ignored if index does not exist
                }
            });
        }

        if (Schema::hasTable('production_plans')) {
            Schema::table('production_plans', function (Blueprint $table) {
                try {
                    $table->dropUnique('production_plans_tenant_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('production_schedules')) {
            Schema::table('production_schedules', function (Blueprint $table) {
                try {
                    $table->dropUnique('production_schedules_tenant_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('production_requisition_slips')) {
            Schema::table('production_requisition_slips', function (Blueprint $table) {
                try {
                    $table->dropUnique('production_requisition_slips_requisition_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('production_maintenance_work_orders')) {
            Schema::table('production_maintenance_work_orders', function (Blueprint $table) {
                try {
                    $table->dropUnique('uniq_mwo_tenant_number');
                } catch (\Throwable) {
                }
            });
        }

        // 3. Renumber existing orders per branch sequentially so each branch has clean reset numbers
        if (Schema::hasTable('production_orders')) {
            $year = date('Y');
            $branches = DB::table('production_orders')->select('tenant_id', 'branch_id')->distinct()->get();
            foreach ($branches as $b) {
                $orders = DB::table('production_orders')
                    ->where('tenant_id', $b->tenant_id)
                    ->where('branch_id', $b->branch_id)
                    ->orderBy('id', 'asc')
                    ->get();

                $seq = 1;
                foreach ($orders as $ord) {
                    $newNum = 'ORD-' . $year . '-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
                    DB::table('production_orders')->where('id', $ord->id)->update(['order_number' => $newNum]);
                    $seq++;
                }
            }
        }

        // Renumber existing requisition slips per branch
        if (Schema::hasTable('production_requisition_slips')) {
            $year = date('Y');
            $branches = DB::table('production_requisition_slips')->select('tenant_id', 'branch_id')->distinct()->get();
            foreach ($branches as $b) {
                $slips = DB::table('production_requisition_slips')
                    ->where('tenant_id', $b->tenant_id)
                    ->where('branch_id', $b->branch_id)
                    ->orderBy('id', 'asc')
                    ->get();

                $seq = 1;
                foreach ($slips as $sl) {
                    $newNum = 'MR-' . $year . '-' . str_pad((string)$seq, 6, '0', STR_PAD_LEFT);
                    DB::table('production_requisition_slips')->where('id', $sl->id)->update(['requisition_number' => $newNum]);
                    $seq++;
                }
            }
        }

        // 4. Add branch-scoped unique constraints
        if (Schema::hasTable('production_orders')) {
            Schema::table('production_orders', function (Blueprint $table) {
                $table->unique(['tenant_id', 'branch_id', 'order_number'], 'prod_orders_tenant_branch_num_uniq');
            });
        }

        if (Schema::hasTable('production_plans')) {
            Schema::table('production_plans', function (Blueprint $table) {
                $table->unique(['tenant_id', 'branch_id', 'plan_number'], 'prod_plans_tenant_branch_num_uniq');
            });
        }

        if (Schema::hasTable('production_schedules')) {
            Schema::table('production_schedules', function (Blueprint $table) {
                $table->unique(['tenant_id', 'branch_id', 'schedule_number'], 'prod_sched_tenant_branch_num_uniq');
            });
        }

        if (Schema::hasTable('production_requisition_slips')) {
            Schema::table('production_requisition_slips', function (Blueprint $table) {
                $table->unique(['tenant_id', 'branch_id', 'requisition_number'], 'prod_req_slips_tenant_branch_num_uniq');
            });
        }

        if (Schema::hasTable('production_maintenance_work_orders')) {
            Schema::table('production_maintenance_work_orders', function (Blueprint $table) {
                $table->unique(['tenant_id', 'branch_id', 'work_order_number'], 'prod_mwo_tenant_branch_num_uniq');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_orders')) {
            Schema::table('production_orders', function (Blueprint $table) {
                try {
                    $table->dropUnique('prod_orders_tenant_branch_num_uniq');
                    $table->unique(['tenant_id', 'order_number'], 'production_orders_tenant_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('production_plans')) {
            Schema::table('production_plans', function (Blueprint $table) {
                try {
                    $table->dropUnique('prod_plans_tenant_branch_num_uniq');
                    $table->unique(['tenant_id', 'plan_number'], 'production_plans_tenant_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('production_schedules')) {
            Schema::table('production_schedules', function (Blueprint $table) {
                try {
                    $table->dropUnique('prod_sched_tenant_branch_num_uniq');
                    $table->unique(['tenant_id', 'schedule_number'], 'production_schedules_tenant_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('production_requisition_slips')) {
            Schema::table('production_requisition_slips', function (Blueprint $table) {
                try {
                    $table->dropUnique('prod_req_slips_tenant_branch_num_uniq');
                    $table->unique('requisition_number', 'production_requisition_slips_requisition_number_unique');
                } catch (\Throwable) {
                }
            });
        }

        if (Schema::hasTable('production_maintenance_work_orders')) {
            Schema::table('production_maintenance_work_orders', function (Blueprint $table) {
                try {
                    $table->dropUnique('prod_mwo_tenant_branch_num_uniq');
                    $table->unique(['tenant_id', 'work_order_number'], 'uniq_mwo_tenant_number');
                } catch (\Throwable) {
                }
            });
        }
    }
};
