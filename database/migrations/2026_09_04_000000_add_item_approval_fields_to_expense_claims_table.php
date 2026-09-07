<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('expense_claims', function (Blueprint $table) {
            if (!Schema::hasColumn('expense_claims', 'status')) {
                $table->string('status')->default('pending')->after('receipt_path');
            }
            if (!Schema::hasColumn('expense_claims', 'approved_amount')) {
                $table->decimal('approved_amount', 10, 2)->nullable()->after('status');
            }
            if (!Schema::hasColumn('expense_claims', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('approved_amount');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('expense_claims', function (Blueprint $table) {
            if (Schema::hasColumn('expense_claims', 'status')) {
                $table->dropColumn(['status', 'approved_amount', 'rejection_reason']);
            }
        });
    }
};
