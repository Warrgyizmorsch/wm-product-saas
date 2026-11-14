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
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'planning_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropColumn('planning_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('products') && !Schema::hasColumn('products', 'planning_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('planning_type', 191)->nullable()->default('stock')->after('type');
            });
        }
    }
};
