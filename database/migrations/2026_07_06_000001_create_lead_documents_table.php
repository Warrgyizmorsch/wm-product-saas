<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained('leads')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_type')->nullable();
            $table->string('file_path');
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->json('documents')->nullable()->after('is_customer');
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'documents')) {
                $table->dropColumn('documents');
            }
        });

        Schema::dropIfExists('lead_documents');
    }
};
