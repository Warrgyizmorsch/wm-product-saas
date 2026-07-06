<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_progress_logs', function (Blueprint $table) {
            $table->dateTime('start_time')->nullable()->after('recorded_at');
            $table->dateTime('stop_time')->nullable()->after('start_time');
        });
    }

    public function down(): void
    {
        Schema::table('production_order_progress_logs', function (Blueprint $table) {
            $table->dropColumn(['start_time', 'stop_time']);
        });
    }
};
