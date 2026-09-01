<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_terms')) {
            Schema::dropIfExists('payment_terms');
        }

        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->unsignedBigInteger('branch_id')->nullable()->index();
            $table->string('name');
            $table->string('code')->nullable();
            $table->integer('due_days')->default(0);
            $table->integer('discount_days')->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->unsignedBigInteger('updated_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // Seed default Payment Terms for all existing tenants
        $tenants = DB::table('tenants')->get();
        $defaultTerms = [
            ['name' => 'Immediate / Due on Receipt', 'code' => 'DUE_RECEIPT', 'due_days' => 0, 'description' => 'Payment due immediately upon receipt of invoice/bill.'],
            ['name' => 'Net 15 Days', 'code' => 'NET15', 'due_days' => 15, 'description' => 'Payment due within 15 calendar days.'],
            ['name' => 'Net 30 Days', 'code' => 'NET30', 'due_days' => 30, 'description' => 'Standard Net 30 days payment term.'],
            ['name' => 'Net 45 Days', 'code' => 'NET45', 'due_days' => 45, 'description' => 'Payment due within 45 calendar days.'],
            ['name' => 'Net 60 Days', 'code' => 'NET60', 'due_days' => 60, 'description' => 'Payment due within 60 calendar days.'],
            ['name' => '50% Advance, 50% Delivery', 'code' => 'ADV50_DEL50', 'due_days' => 0, 'description' => '50% payment in advance and 50% upon delivery.'],
        ];

        foreach ($tenants as $tenant) {
            foreach ($defaultTerms as $term) {
                DB::table('payment_terms')->insert([
                    'tenant_id' => $tenant->id,
                    'company_id' => 1,
                    'branch_id' => 1,
                    'name' => $term['name'],
                    'code' => $term['code'],
                    'due_days' => $term['due_days'],
                    'discount_days' => 0,
                    'discount_percentage' => 0.00,
                    'description' => $term['description'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_terms');
    }
};
