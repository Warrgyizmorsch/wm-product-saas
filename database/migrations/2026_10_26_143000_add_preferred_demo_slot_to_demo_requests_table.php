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
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->date('preferred_demo_date')->nullable()->after('modules');
            $table->string('preferred_demo_time', 50)->nullable()->after('preferred_demo_date');
            $table->dateTime('preferred_demo_at')->nullable()->after('preferred_demo_time')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('demo_requests', function (Blueprint $table) {
            $table->dropIndex(['preferred_demo_at']);
            $table->dropColumn(['preferred_demo_date', 'preferred_demo_time', 'preferred_demo_at']);
        });
    }
};
