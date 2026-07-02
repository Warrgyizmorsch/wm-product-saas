<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_bom_items', function (Blueprint $table) {
            $table->foreignId('child_bom_id')->nullable()->after('material_id')->constrained('production_boms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_bom_items', function (Blueprint $table) {
            $table->dropForeign(['child_bom_id']);
            $table->dropColumn('child_bom_id');
        });
    }
};
