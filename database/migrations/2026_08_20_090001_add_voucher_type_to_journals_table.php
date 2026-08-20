<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            // null = plain manual journal; otherwise payment, receipt, contra, credit_note, debit_note.
            // Orthogonal to `source` (which module triggered the posting).
            $table->string('voucher_type')->nullable()->after('source');
            $table->index(['tenant_id', 'voucher_type']);
        });
    }

    public function down(): void
    {
        Schema::table('journals', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'voucher_type']);
            $table->dropColumn('voucher_type');
        });
    }
};
