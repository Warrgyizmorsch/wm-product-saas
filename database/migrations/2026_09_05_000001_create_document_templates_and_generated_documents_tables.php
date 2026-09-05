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
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('company_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnDelete();
            
            $table->foreignId('document_category_id')
                  ->nullable()
                  ->constrained('document_categories')
                  ->nullOnDelete();
            
            $table->string('name');
            $table->string('code');
            $table->string('template_file_path')->nullable();
            
            $table->longText('header_content')->nullable();
            $table->longText('body_content')->nullable();
            $table->longText('footer_content')->nullable();
            $table->text('css_styles')->nullable();
            
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('active'); // active, inactive
            
            $table->timestamps();

            $table->unique(['tenant_id', 'code']);
        });

        Schema::create('generated_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')
                  ->nullable()
                  ->constrained()
                  ->cascadeOnDelete();
            $table->foreignId('employee_id')
                  ->constrained('employees')
                  ->cascadeOnDelete();
            $table->foreignId('document_template_id')
                  ->nullable()
                  ->constrained('document_templates')
                  ->nullOnDelete();
            $table->foreignId('document_master_id')
                  ->nullable()
                  ->constrained('document_masters')
                  ->nullOnDelete();
            
            $table->string('reference_number')->nullable();
            $table->string('title');
            $table->longText('rendered_content');
            $table->string('file_path')->nullable();
            $table->date('issue_date')->nullable();
            
            $table->foreignId('generated_by')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            
            $table->string('status')->default('issued'); // draft, generated, issued, revoked
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_documents');
        Schema::dropIfExists('document_templates');
    }
};
