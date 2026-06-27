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
        Schema::table('leads', function (Blueprint $table) {
            $table->string('industry_type')->nullable()->after('segment');
            $table->string('country')->nullable()->after('industry_type');
            $table->string('state')->nullable()->after('country');
            $table->string('city')->nullable()->after('state');
            $table->text('address')->nullable()->after('city');
            $table->string('product')->nullable()->after('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn([
                'industry_type',
                'country',
                'state',
                'city',
                'address',
                'product',
            ]);
        });
    }
};