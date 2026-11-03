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
        if (Schema::hasTable('attendance_corrections')) {
            Schema::table('attendance_corrections', function (Blueprint $table) {
                if (!Schema::hasColumn('attendance_corrections', 'approved_at')) {
                    $table->timestamp('approved_at')->nullable()->after('approved_by');
                }
                if (!Schema::hasColumn('attendance_corrections', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable();
                }
                if (!Schema::hasColumn('attendance_corrections', 'cancellation_requested_at')) {
                    $table->timestamp('cancellation_requested_at')->nullable();
                }
                if (!Schema::hasColumn('attendance_corrections', 'cancellation_decided_by')) {
                    $table->unsignedBigInteger('cancellation_decided_by')->nullable();
                }
                if (!Schema::hasColumn('attendance_corrections', 'cancellation_decided_at')) {
                    $table->timestamp('cancellation_decided_at')->nullable();
                }
                if (!Schema::hasColumn('attendance_corrections', 'cancellation_denial_reason')) {
                    $table->text('cancellation_denial_reason')->nullable();
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('attendance_corrections')) {
            Schema::table('attendance_corrections', function (Blueprint $table) {
                $columns = [
                    'approved_at',
                    'cancellation_reason',
                    'cancellation_requested_at',
                    'cancellation_decided_by',
                    'cancellation_decided_at',
                    'cancellation_denial_reason',
                ];
                foreach ($columns as $column) {
                    if (Schema::hasColumn('attendance_corrections', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};

