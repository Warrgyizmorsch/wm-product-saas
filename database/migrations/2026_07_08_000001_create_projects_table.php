<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('project_code');
            $table->string('name');
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->string('budget_type')->nullable(); // Fixed, Time & Material
            $table->decimal('budget_amount', 15, 2)->nullable();
            $table->decimal('budget_hours', 10, 2)->nullable();
            $table->string('billing_method')->nullable(); // Project Based, Milestone Based, Task Based, User Based
            $table->string('priority')->default('Medium'); // Low, Medium, High, Critical
            $table->string('status')->default('Draft')->index(); // Draft, Active, On Hold, Completed, Closed
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'project_code'], 'projects_tenant_code_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
