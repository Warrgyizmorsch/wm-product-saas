<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('quotations', 'phone')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->string('phone', 30)->nullable()->after('crm_deal_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('quotations', 'phone')) {
            Schema::table('quotations', function (Blueprint $table) {
                $table->dropColumn('phone');
            });
        }
    }
};
