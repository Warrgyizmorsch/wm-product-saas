<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary TDS (section 192): one payroll run deducts from many employees, so a
 * register row is unique per source document *and* party, not per document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->unique(['tenant_id', 'source_type', 'source_id', 'tds_section_id', 'party_type', 'party_id'], 'tds_deductions_source_party_unique');
        });
        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->dropUnique('tds_deductions_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->unique(['tenant_id', 'source_type', 'source_id', 'tds_section_id'], 'tds_deductions_source_unique');
        });
        Schema::table('tds_deductions', function (Blueprint $table) {
            $table->dropUnique('tds_deductions_source_party_unique');
        });
    }
};
