<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('purchase_requisitions') && !Schema::hasColumn('purchase_requisitions', 'expected_date')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                $table->date('expected_date')->nullable()->after('requisition_date');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('purchase_requisitions') && Schema::hasColumn('purchase_requisitions', 'expected_date')) {
            Schema::table('purchase_requisitions', function (Blueprint $table) {
                $table->dropColumn('expected_date');
            });
        }
    }
};
