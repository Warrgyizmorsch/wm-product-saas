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
        if (Schema::hasTable('document_templates') && !Schema::hasColumn('document_templates', 'requires_signature')) {
            Schema::table('document_templates', function (Blueprint $table) {
                $table->boolean('requires_signature')->default(false)->after('is_default');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('document_templates') && Schema::hasColumn('document_templates', 'requires_signature')) {
            Schema::table('document_templates', function (Blueprint $table) {
                $table->dropColumn('requires_signature');
            });
        }
    }
};
