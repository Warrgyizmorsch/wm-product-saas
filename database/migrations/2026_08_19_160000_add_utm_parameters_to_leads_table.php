<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                if (!Schema::hasColumn('leads', 'utm_source')) {
                    $table->string('utm_source')->nullable()->after('source');
                }
                if (!Schema::hasColumn('leads', 'utm_medium')) {
                    $table->string('utm_medium')->nullable()->after('utm_source');
                }
                if (!Schema::hasColumn('leads', 'utm_campaign')) {
                    $table->string('utm_campaign')->nullable()->after('utm_medium');
                }
                if (!Schema::hasColumn('leads', 'utm_term')) {
                    $table->string('utm_term')->nullable()->after('utm_campaign');
                }
                if (!Schema::hasColumn('leads', 'utm_content')) {
                    $table->string('utm_content')->nullable()->after('utm_term');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('leads')) {
            Schema::table('leads', function (Blueprint $table) {
                $columns = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('leads', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
