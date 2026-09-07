<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_depreciation_schedules')) {
            return;
        }

        Schema::create('asset_depreciation_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->cascadeOnDelete();

            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');
            $table->date('period_start_date');
            $table->date('period_end_date');

            $table->decimal('opening_book_value', 14, 2);
            $table->decimal('depreciation_amount', 14, 2);
            $table->decimal('closing_book_value', 14, 2);
            $table->string('method');

            $table->string('status')->default('draft'); // draft|reviewed|approved|posted

            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('generated_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('posted_at')->nullable();

            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();

            $table->timestamps();

            $table->unique(['tenant_id', 'asset_id', 'period_year', 'period_month'], 'asset_depr_schedule_period_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_depreciation_schedules');
    }
};
