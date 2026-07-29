<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('quotations', 'rejection_reason')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quotations', 'rejection_reason')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};
