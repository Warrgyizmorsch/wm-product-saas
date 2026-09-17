<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->unsignedBigInteger('document_template_id')->nullable()->after('offered_department_id');
            $table->longText('offer_letter_content')->nullable()->after('offer_letter_notes');

            $table->foreign('document_template_id')->references('id')->on('document_templates')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('job_offers', function (Blueprint $table) {
            $table->dropForeign(['document_template_id']);
            $table->dropColumn(['document_template_id', 'offer_letter_content']);
        });
    }
};
