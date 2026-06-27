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
        Schema::create('lead_followups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lead_id')->constrained()->cascadeOnDelete();
            $table->dateTime('followup_date');
            $table->string('type'); // Call, Email, Meeting, Demo
            $table->string('status')->default('Pending'); // Pending, Completed, Cancelled
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes for faster lookups
            $table->index(['tenant_id', 'lead_id']);
            $table->index(['tenant_id', 'followup_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_followups');
    }
};
