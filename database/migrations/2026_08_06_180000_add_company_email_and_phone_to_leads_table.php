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
            if (!Schema::hasColumn('leads', 'company_email')) {
                $table->string('company_email')->nullable()->after('company_name');
            }
            if (!Schema::hasColumn('leads', 'company_phone')) {
                $table->string('company_phone')->nullable()->after('company_email');
            }
            if (!Schema::hasColumn('leads', 'gstin')) {
                $table->string('gstin')->nullable()->after('company_phone');
            }
            if (!Schema::hasColumn('leads', 'lead_type')) {
                $table->string('lead_type')->default('b2b')->after('gstin');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['company_email', 'company_phone', 'gstin', 'lead_type']);
        });
    }
};
