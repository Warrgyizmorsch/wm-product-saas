<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                try {
                    $table->dropUnique('projects_tenant_code_unique');
                } catch (\Throwable) {
                }

                $table->unique(['tenant_id', 'branch_id', 'project_code'], 'projects_tenant_branch_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('projects')) {
            Schema::table('projects', function (Blueprint $table) {
                try {
                    $table->dropUnique('projects_tenant_branch_code_unique');
                    $table->unique(['tenant_id', 'project_code'], 'projects_tenant_code_unique');
                } catch (\Throwable) {
                }
            });
        }
    }
};
