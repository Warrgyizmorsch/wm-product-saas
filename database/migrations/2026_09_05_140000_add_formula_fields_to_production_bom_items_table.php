<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('production_bom_items', function (Blueprint $table) {
            if (!Schema::hasColumn('production_bom_items', 'quantity_type')) {
                $table->string('quantity_type', 20)->default('fixed')->after('quantity');
            }
            if (!Schema::hasColumn('production_bom_items', 'formula')) {
                $table->string('formula', 255)->nullable()->after('quantity_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_bom_items', function (Blueprint $table) {
            $table->dropColumn(['quantity_type', 'formula']);
        });
    }
};
