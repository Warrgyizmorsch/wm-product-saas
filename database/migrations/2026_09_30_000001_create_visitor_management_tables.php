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
        if (!Schema::hasTable('visitors')) {
            Schema::create('visitors', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('company_id')->default(1)->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('visitor_code', 50)->nullable()->index();
                $table->string('full_name');
                $table->string('phone', 30)->index();
                $table->string('email')->nullable()->index();
                $table->string('company_name')->nullable();
                $table->string('designation')->nullable();
                $table->string('id_proof_type', 50)->nullable();
                $table->string('id_proof_number', 100)->nullable();
                $table->longText('photo_url')->nullable();
                $table->string('status', 30)->default('Active'); // Active, Inactive, Blocked
                $table->boolean('is_blacklisted')->default(false)->index();
                $table->text('blacklist_reason')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('visitor_passes')) {
            Schema::create('visitor_passes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('company_id')->default(1)->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->string('pass_number', 50)->index();
                $table->unsignedBigInteger('visitor_id')->index();
                $table->unsignedBigInteger('host_user_id')->nullable()->index();
                $table->unsignedBigInteger('department_id')->nullable()->index();
                $table->string('purpose', 100)->default('Meeting'); // Meeting, Interview, Vendor, Delivery, Audit, Personal
                $table->string('entry_type', 50)->default('Walk-in'); // Walk-in, Pre-Invite, Kiosk
                $table->string('status', 50)->default('Expected')->index(); // Expected, Waiting Approval, Approved, Checked-In, Checked-Out, Rejected, Cancelled
                $table->timestamp('expected_arrival_at')->nullable();
                $table->timestamp('check_in_at')->nullable();
                $table->timestamp('check_out_at')->nullable();
                $table->string('qr_token', 100)->nullable()->unique();
                $table->boolean('badge_printed')->default(false);
                $table->text('rejection_reason')->nullable();
                $table->string('gate_number', 50)->nullable();
                $table->decimal('fee_amount', 12, 2)->default(0.00);
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('visitor_id')->references('id')->on('visitors')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('visitor_belongings')) {
            Schema::create('visitor_belongings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->unsignedBigInteger('visitor_pass_id')->index();
                $table->string('item_type', 50)->default('Laptop'); // Laptop, Tool, Camera, Vehicle, Sample
                $table->string('brand_model')->nullable();
                $table->string('serial_number')->nullable();
                $table->integer('quantity')->default(1);
                $table->boolean('is_verified_on_exit')->default(false);
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->foreign('visitor_pass_id')->references('id')->on('visitor_passes')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitor_belongings');
        Schema::dropIfExists('visitor_passes');
        Schema::dropIfExists('visitors');
    }
};
