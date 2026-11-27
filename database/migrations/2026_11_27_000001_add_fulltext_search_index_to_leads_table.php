<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->fullText(['contact_person', 'company_name'], 'leads_contact_company_fulltext');
        });
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->dropFullText('leads_contact_company_fulltext');
        });
    }
};
