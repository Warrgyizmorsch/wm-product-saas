<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('asset_disposals')) {
            return;
        }

        Schema::create('asset_disposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();

            $table->string('disposal_type'); // sale|scrap|lost
            $table->date('disposal_date');

            $table->decimal('original_cost', 14, 2);
            $table->decimal('accumulated_depreciation_at_disposal', 14, 2);
            $table->decimal('net_book_value', 14, 2);
            $table->decimal('sale_proceeds', 14, 2)->default(0);
            $table->decimal('tax_amount', 14, 2)->default(0);
            $table->decimal('gain_loss_amount', 14, 2)->default(0);

            $table->string('status')->default('draft'); // draft|pending_approval|approved|rejected|posted

            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();

            $table->foreignId('journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_disposals');
    }
};
