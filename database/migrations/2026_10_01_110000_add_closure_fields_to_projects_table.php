<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('closure_date')->nullable()->after('status');
            $table->string('closure_status', 50)->nullable()->after('closure_date'); // Completed, Terminated, Handed Over
            $table->string('client_approval_ref', 255)->nullable()->after('closure_status');
            $table->text('final_remarks')->nullable()->after('client_approval_ref');
            $table->foreignId('closed_by')->nullable()->after('final_remarks')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropColumn([
                'closure_date',
                'closure_status',
                'client_approval_ref',
                'final_remarks',
                'closed_by',
            ]);
        });
    }
};
