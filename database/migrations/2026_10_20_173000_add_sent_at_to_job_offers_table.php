<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('job_offers') && !Schema::hasColumn('job_offers', 'sent_at')) {
            Schema::table('job_offers', function (Blueprint $table) {
                $table->timestamp('sent_at')->nullable()->after('accepted_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('job_offers') && Schema::hasColumn('job_offers', 'sent_at')) {
            Schema::table('job_offers', function (Blueprint $table) {
                $table->dropColumn('sent_at');
            });
        }
    }
};
