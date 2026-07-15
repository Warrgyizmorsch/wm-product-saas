<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_calendar_holidays', function (Blueprint $table) {
            $table->text('description')->nullable()->after('holiday_type');
            $table->boolean('is_full_day')->default(true)->after('description');
            $table->time('start_time')->nullable()->after('is_full_day');
            $table->time('end_time')->nullable()->after('start_time');
            $table->boolean('active')->default(true)->after('end_time');
        });
    }

    public function down(): void
    {
        Schema::table('production_calendar_holidays', function (Blueprint $table) {
            $table->dropColumn(['description', 'is_full_day', 'start_time', 'end_time', 'active']);
        });
    }
};
