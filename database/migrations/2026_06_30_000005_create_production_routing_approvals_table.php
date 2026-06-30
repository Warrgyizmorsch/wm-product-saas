<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Immutable audit log — same pattern as production_bom_approvals
        // No SoftDeletes intentional: approval history is a permanent compliance record
        Schema::create('production_routing_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();

            $table->foreignId('routing_id')
                ->constrained('routings')
                ->cascadeOnDelete();

            $table->foreignId('user_id')->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Action taken: Created | Submitted | Approved | Rejected | Cancelled | Revision Created
            $table->string('action');
            $table->text('comments')->nullable();

            // Append-only log — created_at only, no updated_at
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tenant_id', 'routing_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_routing_approvals');
    }
};
