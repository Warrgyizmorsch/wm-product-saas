<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('uoms', 'category')) {
            Schema::table('uoms', function (Blueprint $table) {
                $table->string('category')->default('Goods')->after('code');
            });
        }

        // Categorize existing UOM records in DB
        $serviceCodes = ['HRS', 'DAYS', 'VST', 'JOB', 'SES', 'UNTS', 'MTH', 'YRS'];
        
        DB::table('uoms')
            ->whereIn('code', $serviceCodes)
            ->update(['category' => 'Service']);

        DB::table('uoms')
            ->whereNotIn('code', $serviceCodes)
            ->update(['category' => 'Goods']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('uoms', 'category')) {
            Schema::table('uoms', function (Blueprint $table) {
                $table->dropColumn('category');
            });
        }
    }
};
