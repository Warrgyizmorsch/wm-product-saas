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
        if (Schema::hasTable('asset_requests')) {
            Schema::table('asset_requests', function (Blueprint $table) {
                if (!Schema::hasColumn('asset_requests', 'requested_asset_id')) {
                    $table->unsignedBigInteger('requested_asset_id')->nullable()->after('asset_category_id');
                    $table->foreign('requested_asset_id')->references('id')->on('assets')->nullOnDelete();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('asset_requests')) {
            Schema::table('asset_requests', function (Blueprint $table) {
                if (Schema::hasColumn('asset_requests', 'requested_asset_id')) {
                    try {
                        $table->dropForeign(['requested_asset_id']);
                    } catch (\Exception $e) {}
                    $table->dropColumn('requested_asset_id');
                }
            });
        }
    }
};
