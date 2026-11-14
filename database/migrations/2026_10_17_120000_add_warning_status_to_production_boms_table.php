<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('production_boms')) {
            Schema::table('production_boms', function (Blueprint $table) {
                if (!Schema::hasColumn('production_boms', 'has_warning')) {
                    $table->boolean('has_warning')->default(false)->after('status')->index();
                }
                if (!Schema::hasColumn('production_boms', 'warning_reason')) {
                    $table->text('warning_reason')->nullable()->after('has_warning');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('production_boms')) {
            Schema::table('production_boms', function (Blueprint $table) {
                if (Schema::hasColumn('production_boms', 'warning_reason')) {
                    $table->dropColumn('warning_reason');
                }
                if (Schema::hasColumn('production_boms', 'has_warning')) {
                    $table->dropColumn('has_warning');
                }
            });
        }
    }
};
