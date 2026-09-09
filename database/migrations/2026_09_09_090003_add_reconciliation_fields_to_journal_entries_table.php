<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->boolean('is_reconciled')->default(false)->after('cost_center_id');
            $table->timestamp('reconciled_at')->nullable()->after('is_reconciled');
            $table->foreignId('bank_reconciliation_id')->nullable()->after('reconciled_at')
                ->constrained('bank_reconciliations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('journal_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('bank_reconciliation_id');
            $table->dropColumn(['is_reconciled', 'reconciled_at']);
        });
    }
};
