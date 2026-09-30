<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('project_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->nullableMorphs('attachable'); // attachable_type, attachable_id
            $table->string('title');
            $table->string('file_name');
            $table->string('file_path', 500);
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 128);
            $table->string('category', 64)->default('Attachment');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'project_id', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};
