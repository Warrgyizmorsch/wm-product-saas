<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leave_encashments') && !Schema::hasColumn('leave_encashments', 'rejection_reason')) {
            Schema::table('leave_encashments', function (Blueprint $table): void {
                $table->text('rejection_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leave_encashments') && Schema::hasColumn('leave_encashments', 'rejection_reason')) {
            Schema::table('leave_encashments', function (Blueprint $table): void {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};
