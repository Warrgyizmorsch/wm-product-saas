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
        Schema::table('employees', function (Blueprint $table) {
            $table->decimal('wfh_latitude', 10, 8)->nullable()->after('office');
            $table->decimal('wfh_longitude', 11, 8)->nullable()->after('wfh_latitude');
        });

        Schema::table('wfh_requests', function (Blueprint $table) {
            $table->decimal('wfh_latitude', 10, 8)->nullable()->after('reason');
            $table->decimal('wfh_longitude', 11, 8)->nullable()->after('wfh_latitude');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['wfh_latitude', 'wfh_longitude']);
        });

        Schema::table('wfh_requests', function (Blueprint $table) {
            $table->dropColumn(['wfh_latitude', 'wfh_longitude']);
        });
    }
};
