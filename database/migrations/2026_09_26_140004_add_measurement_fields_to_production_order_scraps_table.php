<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_scraps', function (Blueprint $table) {
            $table->string('measurement_type', 30)->nullable()->after('scrap_type');
            $table->decimal('length', 12, 4)->nullable()->after('measurement_type');
            $table->decimal('width', 12, 4)->nullable()->after('length');
            $table->decimal('thickness', 10, 4)->nullable()->after('width');
            $table->integer('pieces')->nullable()->default(1)->after('thickness');
            $table->decimal('weight', 12, 4)->nullable()->after('pieces');
            $table->string('weight_unit', 10)->nullable()->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_scraps', function (Blueprint $table) {
            $table->dropColumn([
                'measurement_type',
                'length',
                'width',
                'thickness',
                'pieces',
                'weight',
                'weight_unit',
            ]);
        });
    }
};
