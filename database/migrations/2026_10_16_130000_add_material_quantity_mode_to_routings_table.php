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
        Schema::table('routings', function (Blueprint $table) {
            if (!Schema::hasColumn('routings', 'material_quantity_mode')) {
                $table->string('material_quantity_mode', 50)
                    ->default('per_unit')
                    ->after('bom_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routings', function (Blueprint $table) {
            if (Schema::hasColumn('routings', 'material_quantity_mode')) {
                $table->dropColumn('material_quantity_mode');
            }
        });
    }
};
