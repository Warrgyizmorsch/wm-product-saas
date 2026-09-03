<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_order_reservations', function (Blueprint $table) {
            if (! Schema::hasColumn('production_order_reservations', 'quantity_additional_requested')) {
                $table->decimal('quantity_additional_requested', 12, 4)->default(0.0000)->after('quantity_planned');
            }
        });
    }

    public function down(): void
    {
        Schema::table('production_order_reservations', function (Blueprint $table) {
            if (Schema::hasColumn('production_order_reservations', 'quantity_additional_requested')) {
                $table->dropColumn('quantity_additional_requested');
            }
        });
    }
};
