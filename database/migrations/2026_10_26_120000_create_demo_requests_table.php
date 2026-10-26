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
        Schema::create('demo_requests', function (Blueprint $table) {
            $table->id();
            $table->string('full_name');
            $table->string('email');
            $table->string('phone', 50);
            $table->string('company_name')->nullable();
            $table->string('team_size', 100)->nullable();
            $table->json('modules')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 50)->default('new')->index();
            $table->text('admin_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('converted_tenant_id')
                ->nullable()
                ->constrained('tenants')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['created_at', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demo_requests');
    }
};
