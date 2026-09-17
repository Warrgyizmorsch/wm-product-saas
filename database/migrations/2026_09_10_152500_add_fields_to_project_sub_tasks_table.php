<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_sub_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('project_sub_tasks', 'start_date')) {
                $table->date('start_date')->nullable()->after('assignee_id');
            }
            if (! Schema::hasColumn('project_sub_tasks', 'due_date')) {
                $table->date('due_date')->nullable()->after('start_date');
            }
            if (! Schema::hasColumn('project_sub_tasks', 'estimated_hours')) {
                $table->decimal('estimated_hours', 8, 2)->nullable()->after('due_date');
            }
            if (! Schema::hasColumn('project_sub_tasks', 'status')) {
                $table->string('status')->default('Open')->after('estimated_hours');
                $table->index(['tenant_id', 'status']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('project_sub_tasks', function (Blueprint $table) {
            $columnsToDrop = [];
            if (Schema::hasColumn('project_sub_tasks', 'status')) {
                $table->dropIndex(['tenant_id', 'status']);
                $columnsToDrop[] = 'status';
            }
            if (Schema::hasColumn('project_sub_tasks', 'estimated_hours')) {
                $columnsToDrop[] = 'estimated_hours';
            }
            if (Schema::hasColumn('project_sub_tasks', 'due_date')) {
                $columnsToDrop[] = 'due_date';
            }
            if (Schema::hasColumn('project_sub_tasks', 'start_date')) {
                $columnsToDrop[] = 'start_date';
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
