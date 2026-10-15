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
            if (!Schema::hasColumn('routings', 'bom_id')) {
                $table->foreignId('bom_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('production_boms')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routings', function (Blueprint $table) {
            if (Schema::hasColumn('routings', 'bom_id')) {
                $table->dropForeign(['bom_id']);
                $table->dropColumn('bom_id');
            }
        });
    }
};
