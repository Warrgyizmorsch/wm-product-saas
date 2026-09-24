<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** One layout per dashboard: 'common' (the workspace dashboard), 'accounting', and so on. */
    public function up(): void
    {
        Schema::table('dashboard_layouts', function (Blueprint $table): void {
            $table->string('dashboard', 40)->default('common')->after('tenant_id');
        });

        Schema::table('dashboard_layouts', function (Blueprint $table): void {
            $table->dropUnique('dashboard_layouts_scope_unique');
            $table->unique(['tenant_id', 'dashboard', 'user_id', 'role_id'], 'dashboard_layouts_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_layouts', function (Blueprint $table): void {
            $table->dropUnique('dashboard_layouts_scope_unique');
            $table->unique(['tenant_id', 'user_id', 'role_id'], 'dashboard_layouts_scope_unique');
            $table->dropColumn('dashboard');
        });
    }
};
