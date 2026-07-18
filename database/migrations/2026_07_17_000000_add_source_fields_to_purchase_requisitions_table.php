<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table) {
            if (!Schema::hasColumn('purchase_requisitions', 'source_type')) {
                $table->string('source_type')->default('direct')->after('status'); // direct, so, mo, material_request, material_requirement, requisition_slip
            }
            if (!Schema::hasColumn('purchase_requisitions', 'source_id')) {
                $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            }
            if (!Schema::hasColumn('purchase_requisitions', 'requisition_slip_number')) {
                $table->string('requisition_slip_number')->nullable()->after('source_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requisitions', function (Blueprint $table) {
            $table->dropColumn([
                'source_type',
                'source_id',
                'requisition_slip_number',
            ]);
        });
    }
};
