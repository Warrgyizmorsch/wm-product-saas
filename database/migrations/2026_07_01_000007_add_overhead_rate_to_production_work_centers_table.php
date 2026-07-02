<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_work_centers', function (Blueprint $table) {
            $table->decimal('overhead_rate', 12, 4)->default(0.0000)->after('cost_per_hour');
        });
    }

    public function down(): void
    {
        Schema::table('production_work_centers', function (Blueprint $table) {
            $table->dropColumn('overhead_rate');
        });
    }
};
