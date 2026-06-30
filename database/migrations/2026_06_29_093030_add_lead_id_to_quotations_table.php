<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds lead_id to quotations so each quotation is linked to a specific lead.
     * This fixes the issue where same-email leads would all share the same quotations.
     */
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Add lead_id as a nullable unsigned bigint (no FK constraint for SQLite compat)
            $table->unsignedBigInteger('lead_id')->nullable()->after('customer_id');

            // Add foreign key only for non-SQLite databases
            if (config('database.default') !== 'sqlite') {
                $table->foreign('lead_id')
                    ->references('id')
                    ->on('leads')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (config('database.default') !== 'sqlite') {
                $table->dropForeign(['lead_id']);
            }
            $table->dropColumn('lead_id');
        });
    }
};
