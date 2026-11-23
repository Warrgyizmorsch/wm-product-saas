<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FRS 2.5 completion:
     *  - hold / customer-requested pause with review date (additional functional controls)
     *  - merge lineage (duplicate merge keeps all history)
     *  - communication opt-out (acknowledgement subject to permissions / consent)
     */
    public function up(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'on_hold_until')) {
                $table->date('on_hold_until')->nullable()->index();
            }
            if (!Schema::hasColumn('leads', 'hold_type')) {
                $table->string('hold_type', 30)->nullable();
            }
            if (!Schema::hasColumn('leads', 'hold_reason')) {
                $table->string('hold_reason', 500)->nullable();
            }
            if (!Schema::hasColumn('leads', 'hold_set_at')) {
                $table->timestamp('hold_set_at')->nullable();
            }
            if (!Schema::hasColumn('leads', 'hold_set_by')) {
                $table->unsignedBigInteger('hold_set_by')->nullable();
            }
            if (!Schema::hasColumn('leads', 'merged_into_lead_id')) {
                $table->unsignedBigInteger('merged_into_lead_id')->nullable()->index();
            }
            if (!Schema::hasColumn('leads', 'communication_opt_out')) {
                $table->boolean('communication_opt_out')->default(false);
            }
            if (!Schema::hasColumn('leads', 'communication_opt_out_at')) {
                $table->timestamp('communication_opt_out_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('leads')) {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            foreach (['on_hold_until', 'hold_type', 'hold_reason', 'hold_set_at', 'hold_set_by', 'merged_into_lead_id', 'communication_opt_out', 'communication_opt_out_at'] as $col) {
                if (Schema::hasColumn('leads', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
