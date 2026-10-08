<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_maintenance_work_order_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('production_maintenance_work_order_assignments', 'expected_work_hours')) {
                $table->decimal('expected_work_hours', 8, 2)->default(0.00)->after('assigned_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_maintenance_work_order_assignments', function (Blueprint $table) {
            if (Schema::hasColumn('production_maintenance_work_order_assignments', 'expected_work_hours')) {
                $table->dropColumn('expected_work_hours');
            }
        });
    }
};
