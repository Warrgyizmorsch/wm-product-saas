<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_task_dependencies', function (Blueprint $table) {
            if (! Schema::hasColumn('project_task_dependencies', 'dependency_type')) {
                $table->string('dependency_type')->default('Finish-to-Start')->after('depends_on_task_id');
                $table->index(['tenant_id', 'dependency_type']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_task_dependencies', function (Blueprint $table) {
            if (Schema::hasColumn('project_task_dependencies', 'dependency_type')) {
                $table->dropIndex(['tenant_id', 'dependency_type']);
                $table->dropColumn('dependency_type');
            }
        });
    }
};
