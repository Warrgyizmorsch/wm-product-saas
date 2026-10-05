<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_task_dependencies', function (Blueprint $table) {
            if (! Schema::hasColumn('project_task_dependencies', 'lag_days')) {
                $table->integer('lag_days')->default(0)->after('dependency_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_task_dependencies', function (Blueprint $table) {
            if (Schema::hasColumn('project_task_dependencies', 'lag_days')) {
                $table->dropColumn('lag_days');
            }
        });
    }
};
