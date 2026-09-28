<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ledger_groups')) {
            Schema::create('ledger_groups', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('code');
                $table->string('name');
                $table->foreignId('parent_id')->nullable()->constrained('ledger_groups')->nullOnDelete();
                $table->string('nature');
                $table->boolean('is_system')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['tenant_id', 'code']);
                $table->index(['tenant_id', 'parent_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_groups');
    }
};
