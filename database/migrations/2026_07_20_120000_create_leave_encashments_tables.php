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
        Schema::create('leave_encashments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('company_id')->nullable()->index();
            $table->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $table->foreignId('leave_type_id')->constrained('leave_types')->onDelete('cascade');
            $table->decimal('requested_days', 8, 1);
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('reason')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        if (Schema::hasTable('leave_balances') && !Schema::hasColumn('leave_balances', 'encashed')) {
            Schema::table('leave_balances', function (Blueprint $table): void {
                $table->decimal('encashed', 8, 1)->default(0.0)->after('used');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leave_encashments');

        if (Schema::hasTable('leave_balances') && Schema::hasColumn('leave_balances', 'encashed')) {
            Schema::table('leave_balances', function (Blueprint $table): void {
                $table->dropColumn('encashed');
            });
        }
    }
};
