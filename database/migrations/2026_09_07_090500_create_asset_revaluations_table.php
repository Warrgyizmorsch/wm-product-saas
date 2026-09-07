<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_revaluations')) {
            return;
        }

        Schema::create('asset_revaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();

            $table->date('revaluation_date');
            $table->decimal('previous_book_value', 14, 2);
            $table->decimal('revalued_amount', 14, 2);
            $table->decimal('revaluation_surplus_deficit', 14, 2); // +ve surplus, -ve deficit/impairment

            $table->unsignedInteger('revised_useful_life_months')->nullable();
            $table->decimal('revised_residual_value', 14, 2)->nullable();

            $table->text('reason');
            $table->string('status')->default('draft'); // draft|pending_approval|approved|rejected|posted

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_revaluations');
    }
};
