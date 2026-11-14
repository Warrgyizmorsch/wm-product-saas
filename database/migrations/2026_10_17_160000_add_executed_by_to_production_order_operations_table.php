<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_order_operations')) {
            Schema::table('production_order_operations', function (Blueprint $table) {
                if (!Schema::hasColumn('production_order_operations', 'executed_by')) {
                    $table->foreignId('executed_by')->nullable()->after('operator_id')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_order_operations')) {
            Schema::table('production_order_operations', function (Blueprint $table) {
                if (Schema::hasColumn('production_order_operations', 'executed_by')) {
                    $table->dropForeign(['executed_by']);
                    $table->dropColumn('executed_by');
                }
            });
        }
    }
};
