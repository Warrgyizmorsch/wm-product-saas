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
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                if (!Schema::hasColumn('documents', 'is_signed')) {
                    $table->boolean('is_signed')->default(false)->after('status');
                }
                if (!Schema::hasColumn('documents', 'requires_signature')) {
                    $table->boolean('requires_signature')->default(false)->after('is_signed');
                }
                if (!Schema::hasColumn('documents', 'signed_at')) {
                    $table->timestamp('signed_at')->nullable()->after('requires_signature');
                }
                if (!Schema::hasColumn('documents', 'signed_by_id')) {
                    $table->foreignId('signed_by_id')->nullable()->constrained('users')->nullOnDelete()->after('signed_at');
                }
                if (!Schema::hasColumn('documents', 'signature_ip')) {
                    $table->string('signature_ip')->nullable()->after('signed_by_id');
                }
                if (!Schema::hasColumn('documents', 'signed_file_path')) {
                    $table->string('signed_file_path')->nullable()->after('signature_ip');
                }
                if (!Schema::hasColumn('documents', 'signature_metadata')) {
                    $table->json('signature_metadata')->nullable()->after('signed_file_path');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('documents')) {
            Schema::table('documents', function (Blueprint $table) {
                $columns = ['is_signed', 'requires_signature', 'signed_at', 'signed_by_id', 'signature_ip', 'signed_file_path', 'signature_metadata'];
                foreach ($columns as $col) {
                    if (Schema::hasColumn('documents', $col)) {
                        if ($col === 'signed_by_id') {
                            $table->dropForeign(['signed_by_id']);
                        }
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
