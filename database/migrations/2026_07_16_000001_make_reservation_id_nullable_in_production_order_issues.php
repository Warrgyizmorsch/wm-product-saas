<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The production_order_issues table was originally created with reservation_id
 * as a NOT NULL FK to production_order_reservations. However, issues raised from
 * the Material Request (Requisition Slip) flow do NOT go through the
 * production_order_reservations table — they use the StockReservation (inventory)
 * layer instead. Making reservation_id nullable allows both flows to insert
 * records without a constraint violation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_issues', function (Blueprint $table) {
            // Drop the existing FK constraint first, then re-add as nullable
            $table->dropForeign(['reservation_id']);
            $table->foreignId('reservation_id')
                ->nullable()
                ->change();
            $table->foreign('reservation_id')
                ->references('id')
                ->on('production_order_reservations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_order_issues', function (Blueprint $table) {
            $table->dropForeign(['reservation_id']);
            $table->foreignId('reservation_id')
                ->nullable(false)
                ->change();
            $table->foreign('reservation_id')
                ->references('id')
                ->on('production_order_reservations')
                ->cascadeOnDelete();
        });
    }
};
