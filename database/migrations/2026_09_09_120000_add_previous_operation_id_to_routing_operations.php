<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->foreignId('previous_operation_id')
                ->nullable()
                ->after('operation_number')
                ->constrained('production_routing_operations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_routing_operations', function (Blueprint $table) {
            $table->dropForeign(['previous_operation_id']);
            $table->dropColumn('previous_operation_id');
        });
    }
};
