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
        // 1. Expense Categories
        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->string('code');
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
 
            $table->unique(['tenant_id', 'code']);
        });
 
        // 2. Expense Policies (Named Policy Headers)
        Schema::create('expense_policies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedBigInteger('designation_id')->nullable();
            $table->unsignedBigInteger('department_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('business_unit_id')->nullable();
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        // 2b. Expense Policy Rules (Category Limits inside a Policy)
        Schema::create('expense_policy_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_policy_id')->constrained('expense_policies')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->decimal('max_limit_per_claim', 10, 2)->nullable();
            $table->decimal('max_daily_limit', 10, 2)->nullable();
            $table->decimal('max_monthly_limit', 10, 2)->nullable();
            $table->decimal('receipt_required_threshold', 10, 2)->nullable();
            $table->boolean('receipt_required')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['expense_policy_id', 'expense_category_id'], 'exp_policy_cat_unique');
        });
 
        // 3. Travel Requests
        Schema::create('travel_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('purpose');
            $table->string('destination');
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('estimated_budget', 10, 2)->default(0.00);
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->timestamps();
        });
 
        // 4. Expense Reports
        Schema::create('expense_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('travel_request_id')->nullable()->constrained('travel_requests')->nullOnDelete();
            $table->string('title');
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->decimal('advance_adjusted', 10, 2)->default(0.00);
            $table->decimal('net_reimbursement', 10, 2)->default(0.00);
            $table->string('status')->default('draft'); // draft, submitted, approved, rejected, paid
            $table->timestamps();
        });
 
        // 5. Cash Advances
        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->foreignId('travel_request_id')->nullable()->constrained('travel_requests')->nullOnDelete();
            $table->foreignId('expense_report_id')->nullable()->constrained('expense_reports')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('purpose');
            $table->string('status')->default('pending'); // pending, approved, rejected, disbursed, settled
            $table->timestamps();
        });
 
        // 6. Expense Claims
        Schema::create('expense_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_report_id')->constrained('expense_reports')->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained('expense_categories')->cascadeOnDelete();
            $table->date('expense_date');
            $table->decimal('amount', 10, 2);
            $table->decimal('tax_amount', 10, 2)->default(0.00);
            $table->string('merchant')->nullable();
            $table->text('description')->nullable();
            $table->string('receipt_path')->nullable();
            $table->timestamps();
        });
    }
 
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_claims');
        Schema::dropIfExists('cash_advances');
        Schema::dropIfExists('expense_reports');
        Schema::dropIfExists('travel_requests');
        Schema::dropIfExists('expense_policy_rules');
        Schema::dropIfExists('expense_policies');
        Schema::dropIfExists('expense_categories');
    }
};
