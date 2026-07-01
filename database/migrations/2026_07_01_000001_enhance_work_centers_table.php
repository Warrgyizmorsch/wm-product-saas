<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_work_centers', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('production_work_centers')->nullOnDelete();
            $table->string('type', 50)->default('work_center')->after('parent_id');
        });
    }

    public function down(): void
    {
        Schema::table('production_work_centers', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['parent_id', 'type']);
        });
    }
};
