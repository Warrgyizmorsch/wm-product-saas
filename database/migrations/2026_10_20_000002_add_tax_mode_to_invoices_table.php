<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('invoices') && !Schema::hasColumn('invoices', 'tax_mode')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->string('tax_mode')->default('exclusive')->after('tax_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('invoices') && Schema::hasColumn('invoices', 'tax_mode')) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('tax_mode');
            });
        }
    }
};
