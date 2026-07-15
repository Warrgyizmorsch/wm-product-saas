<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            // Drop foreign key constraint first, then column
            $table->dropForeign(['customer_id']);
            $table->dropColumn('customer_id');
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->constrained()->cascadeOnDelete();
        });
    }
};
