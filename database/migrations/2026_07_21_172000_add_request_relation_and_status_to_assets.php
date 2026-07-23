<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('assets') && !Schema::hasColumn('assets', 'asset_request_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->foreignId('asset_request_id')
                      ->nullable()
                      ->constrained('asset_requests')
                      ->nullOnDelete();
            });
        }

        if (Schema::hasTable('asset_requests')) {
            try {
                if (DB::getDriverName() === 'mysql') {
                    DB::statement("ALTER TABLE asset_requests MODIFY COLUMN status VARCHAR(50) DEFAULT 'pending'");
                }
            } catch (\Throwable $e) {
                // Ignore if driver unsupported or already altered
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('assets') && Schema::hasColumn('assets', 'asset_request_id')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->dropForeign(['asset_request_id']);
                $table->dropColumn('asset_request_id');
            });
        }
    }
};
