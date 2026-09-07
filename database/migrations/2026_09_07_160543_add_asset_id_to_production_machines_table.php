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
        Schema::table('production_machines', function (Blueprint $table) {
            if (!Schema::hasColumn('production_machines', 'asset_id')) {
                $table->foreignId('asset_id')
                    ->nullable()
                    ->after('work_center_id')
                    ->constrained('assets')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_machines', function (Blueprint $table) {
            if (Schema::hasColumn('production_machines', 'asset_id')) {
                $table->dropConstrainedForeignId('asset_id');
            }
        });
    }
};
