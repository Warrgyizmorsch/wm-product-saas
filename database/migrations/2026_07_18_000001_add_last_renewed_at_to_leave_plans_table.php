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
        if (Schema::hasTable('leave_plans')) {
            Schema::table('leave_plans', function (Blueprint $table) {
                $table->date('last_renewed_at')->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('leave_plans') && Schema::hasColumn('leave_plans', 'last_renewed_at')) {
            Schema::table('leave_plans', function (Blueprint $table) {
                $table->dropColumn('last_renewed_at');
            });
        }
    }
};
