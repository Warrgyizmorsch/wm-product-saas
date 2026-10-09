<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads') && !Schema::hasColumn('leads', 'custom_fields')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->json('custom_fields')->nullable()->after('documents');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads') && Schema::hasColumn('leads', 'custom_fields')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('custom_fields');
            });
        }
    }
};
