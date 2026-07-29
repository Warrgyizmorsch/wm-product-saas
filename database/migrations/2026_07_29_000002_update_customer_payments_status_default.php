<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Change default from 'Draft' to 'Posted'
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->string('status')->default('Posted')->change();
        });

        // Update all existing Draft payments → Posted
        DB::table('customer_payments')->where('status', 'Draft')->update(['status' => 'Posted']);
    }

    public function down(): void
    {
        Schema::table('customer_payments', function (Blueprint $table) {
            $table->string('status')->default('Draft')->change();
        });
    }
};
