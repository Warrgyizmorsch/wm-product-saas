<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('accounting_period_id')->nullable()->constrained('accounting_periods')->nullOnDelete();
            $table->string('journal_number');
            $table->date('journal_date');
            $table->string('source')->default('manual'); // manual, sales, purchase, inventory, production, payroll
            $table->string('reference_type')->nullable(); // e.g. App\Domains\Sales\Models\Invoice
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('memo')->nullable();
            $table->string('status')->default('posted'); // draft, posted, reversed
            $table->foreignId('reversed_journal_id')->nullable()->constrained('journals')->nullOnDelete();
            $table->decimal('total_debit', 18, 2)->default(0);
            $table->decimal('total_credit', 18, 2)->default(0);
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('posted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'journal_number']);
            $table->index(['tenant_id', 'journal_date']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
