<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounting_posting_failures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained('tenants')->cascadeOnDelete();
            $table->string('event_class');
            $table->string('model_class');
            $table->unsignedBigInteger('model_id');
            $table->text('message');
            $table->timestamp('occurred_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'resolved_at']);
            $table->index(['model_class', 'model_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounting_posting_failures');
    }
};
