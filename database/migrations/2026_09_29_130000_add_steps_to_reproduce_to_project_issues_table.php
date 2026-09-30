<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('project_issues', function (Blueprint $table) {
            $table->text('steps_to_reproduce')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('project_issues', function (Blueprint $table) {
            $table->dropColumn('steps_to_reproduce');
        });
    }
};
