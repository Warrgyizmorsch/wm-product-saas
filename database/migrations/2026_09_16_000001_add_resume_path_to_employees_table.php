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
        if (!Schema::hasColumn('employees', 'resume_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->string('resume_path')->nullable()->after('photo');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('employees', 'resume_path')) {
            Schema::table('employees', function (Blueprint $table) {
                $table->dropColumn('resume_path');
            });
        }
    }
};
