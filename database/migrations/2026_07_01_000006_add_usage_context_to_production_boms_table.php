<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_boms', function (Blueprint $table) {
            $table->string('usage_context')->default('manufacturing')->after('bom_type');
        });
    }

    public function down(): void
    {
        Schema::table('production_boms', function (Blueprint $table) {
            $table->dropColumn('usage_context');
        });
    }
};
