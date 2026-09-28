<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_statement_lines', 'suggested_ledger')) {
                $table->string('suggested_ledger')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (Schema::hasColumn('bank_statement_lines', 'suggested_ledger')) {
                $table->dropColumn('suggested_ledger');
            }
        });
    }
};
