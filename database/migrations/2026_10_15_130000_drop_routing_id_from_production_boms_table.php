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
        Schema::table('production_boms', function (Blueprint $table) {
            if (Schema::hasColumn('production_boms', 'routing_id')) {
                $table->dropForeign(['routing_id']);
                $table->dropColumn('routing_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('production_boms', function (Blueprint $table) {
            if (!Schema::hasColumn('production_boms', 'routing_id')) {
                $table->foreignId('routing_id')->nullable()->after('revision_reason')->constrained('routings')->nullOnDelete();
            }
        });
    }
};
