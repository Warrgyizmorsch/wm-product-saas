<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Smallest number of users a plan can be bought for (Zoho's "Minimum users: 5").
        // Null = no plan minimum; the workspace's current user count still applies.
        Schema::table('plans', function (Blueprint $table) {
            $table->unsignedInteger('min_users')->nullable()->after('max_users');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('min_users');
        });
    }
};
