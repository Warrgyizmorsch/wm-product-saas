<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_tax_rates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('sales_tax'); // sales_tax, gst, vat, withholding
            $table->decimal('rate', 8, 4)->default(0); // percentage, e.g. 18.0000
            $table->boolean('is_compound')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('tax_payable_account_id')->nullable()->constrained('chart_of_accounts')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'name']);
            $table->index(['tenant_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_tax_rates');
    }
};
