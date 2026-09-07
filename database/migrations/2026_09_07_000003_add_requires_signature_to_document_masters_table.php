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
        if (Schema::hasTable('document_masters') && !Schema::hasColumn('document_masters', 'requires_signature')) {
            Schema::table('document_masters', function (Blueprint $table) {
                $table->boolean('requires_signature')->default(false)->after('approval_required');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('document_masters') && Schema::hasColumn('document_masters', 'requires_signature')) {
            Schema::table('document_masters', function (Blueprint $table) {
                $table->dropColumn('requires_signature');
            });
        }
    }
};
