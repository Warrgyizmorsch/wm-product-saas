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
        if (Schema::hasColumn('leads', 'product_id')) {
            Schema::table('leads', function (Blueprint $table) {
                try {
                    $table->dropForeign(['product_id']);
                } catch (\Exception $e) {}
                $table->dropColumn('product_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasColumn('leads', 'product_id')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->unsignedBigInteger('product_id')->nullable()->after('address');
            });
        }
    }
};
