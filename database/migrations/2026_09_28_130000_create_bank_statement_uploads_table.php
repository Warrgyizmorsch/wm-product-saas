<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('bank_statement_uploads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_reconciliation_id')->constrained('bank_reconciliations')->cascadeOnDelete();
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->string('status')->default('pending'); // pending | completed | failed
            $table->string('provider')->nullable();
            $table->unsignedInteger('extracted_count')->default(0);
            $table->json('raw_response')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('extracted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('extracted_at')->nullable();
            $table->timestamps();

            // Explicit short name — the auto-generated one exceeds MySQL's 64-char identifier limit.
            $table->index(['tenant_id', 'bank_reconciliation_id', 'status'], 'bsu_tenant_reconciliation_status_idx');
        });

        Schema::table('bank_statement_lines', function (Blueprint $table) {
            if (!Schema::hasColumn('bank_statement_lines', 'bank_statement_upload_id')) {
                $table->foreignId('bank_statement_upload_id')->nullable()->after('bank_reconciliation_id')
                    ->constrained('bank_statement_uploads')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('bank_statement_lines', 'bank_statement_upload_id')) {
            Schema::table('bank_statement_lines', function (Blueprint $table) {
                $table->dropConstrainedForeignId('bank_statement_upload_id');
            });
        }

        Schema::dropIfExists('bank_statement_uploads');
    }
};
