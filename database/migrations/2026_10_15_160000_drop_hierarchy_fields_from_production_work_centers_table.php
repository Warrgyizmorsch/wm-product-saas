<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_work_centers', function (Blueprint $table) {
            if (Schema::hasColumn('production_work_centers', 'parent_id')) {
                $table->dropForeign(['parent_id']);
                $table->dropColumn('parent_id');
            }
            if (Schema::hasColumn('production_work_centers', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('production_work_centers', 'department_name')) {
                $table->dropColumn('department_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_work_centers', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('production_work_centers')->nullOnDelete();
            $table->string('type', 50)->default('work_center')->after('parent_id');
            $table->string('department_name')->nullable()->after('description');
        });
    }
};
