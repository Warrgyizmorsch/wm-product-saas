<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('material_requirement_items', 'quantity_pr_raised')) {
            Schema::table('material_requirement_items', function (Blueprint $table) {
                $table->decimal('quantity_pr_raised', 15, 4)->default(0.0000)->after('quantity_reserved');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('material_requirement_items', 'quantity_pr_raised')) {
            Schema::table('material_requirement_items', function (Blueprint $table) {
                $table->dropColumn('quantity_pr_raised');
            });
        }
    }
};
