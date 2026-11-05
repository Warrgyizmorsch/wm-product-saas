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
        Schema::table('employee_exits', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_exits', 'feedback_text')) {
                $table->text('feedback_text')->nullable()->after('reason_details');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_exits', function (Blueprint $table) {
            if (Schema::hasColumn('employee_exits', 'feedback_text')) {
                $table->dropColumn('feedback_text');
            }
        });
    }
};

