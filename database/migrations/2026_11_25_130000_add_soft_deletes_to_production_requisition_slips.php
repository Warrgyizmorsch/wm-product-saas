<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M-36: deleting a draft production order cancels its material request(s) (requisition slips) and then soft-deletes them —
 * the slip and its lines stay in the table for history but disappear from every list.
 */
return new class extends Migration
{
    private array $tables = ['production_requisition_slips', 'production_requisition_slip_items'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasTable($name) && !Schema::hasColumn($name, 'deleted_at')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            if (Schema::hasTable($name) && Schema::hasColumn($name, 'deleted_at')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
