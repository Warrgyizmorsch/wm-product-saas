<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->decimal('ordered_qty', 12, 4)->default(0.0000)->after('quantity');
            $table->decimal('shipped_qty', 12, 4)->default(0.0000)->after('ordered_qty');
            $table->decimal('remaining_qty', 12, 4)->default(0.0000)->after('shipped_qty');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_order_items', function (Blueprint $table) {
            $table->dropColumn(['ordered_qty', 'shipped_qty', 'remaining_qty']);
        });
    }
};
