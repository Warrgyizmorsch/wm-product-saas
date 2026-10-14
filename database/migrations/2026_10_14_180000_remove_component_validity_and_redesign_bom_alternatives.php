<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_bom_items', function (Blueprint $table) {
            if (Schema::hasColumn('production_bom_items', 'effective_from')) {
                $table->dropColumn('effective_from');
            }
            if (Schema::hasColumn('production_bom_items', 'effective_to')) {
                $table->dropColumn('effective_to');
            }
            if (Schema::hasColumn('production_bom_items', 'alternative_group')) {
                $table->dropColumn('alternative_group');
            }
            if (!Schema::hasColumn('production_bom_items', 'replaces_item_id')) {
                $table->foreignId('replaces_item_id')
                    ->nullable()
                    ->after('child_bom_id')
                    ->constrained('production_bom_items')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_bom_items', function (Blueprint $table) {
            if (Schema::hasColumn('production_bom_items', 'replaces_item_id')) {
                $table->dropForeign(['replaces_item_id']);
                $table->dropColumn('replaces_item_id');
            }
            if (!Schema::hasColumn('production_bom_items', 'effective_from')) {
                $table->date('effective_from')->nullable()->after('sequence');
            }
            if (!Schema::hasColumn('production_bom_items', 'effective_to')) {
                $table->date('effective_to')->nullable()->after('effective_from');
            }
            if (!Schema::hasColumn('production_bom_items', 'alternative_group')) {
                $table->string('alternative_group')->nullable()->after('is_alternative');
            }
        });
    }
};
