<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_schedule_operations', function (Blueprint $table) {
            $table->dateTime('last_paused_at')->nullable()->after('status');
            $table->unsignedInteger('accumulated_paused_seconds')->default(0)->after('last_paused_at');
        });
    }

    public function down(): void
    {
        Schema::table('production_schedule_operations', function (Blueprint $table) {
            $table->dropColumn(['last_paused_at', 'accumulated_paused_seconds']);
        });
    }
};
