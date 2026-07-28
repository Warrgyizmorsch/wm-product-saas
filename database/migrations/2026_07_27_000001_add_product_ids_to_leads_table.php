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
        if (!Schema::hasColumn('leads', 'product_ids')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->json('product_ids')->nullable()->after('product_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('leads', 'product_ids')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('product_ids');
            });
        }
    }
};
