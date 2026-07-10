<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop any previously created document tables to avoid conflicts
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_types');

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            
            // Polymorphic relation fields
            $table->morphs('documentable'); // documentable_id, documentable_type
            
            $table->string('name'); // e.g. "Aadhar Card", "Offer Letter"
            $table->text('description')->nullable(); // Optional instructions or description
            
            // File upload details (nullable for pending requests)
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            
            // Metadata & Expiry tracking
            $table->string('status')->default('requested'); // requested, uploaded, approved
            $table->boolean('has_expiry')->default(false);
            $table->date('expiry_date')->nullable();
            
            // Audit fields
            $table->foreignId('requested_by_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
