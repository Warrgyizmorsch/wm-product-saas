<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('deal_statuses')) {
            Schema::create('deal_statuses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('name');
                $table->integer('sort_order')->default(0);
                $table->string('color')->default('bg-primary');
                $table->boolean('is_protected')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed default deal stages for tenant 1
            $now = now();
            $defaults = [
                ['tenant_id' => 1, 'name' => 'Qualification',  'sort_order' => 1, 'color' => 'bg-primary',   'is_protected' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => 1, 'name' => 'Needs Analysis', 'sort_order' => 2, 'color' => 'bg-info',      'is_protected' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => 1, 'name' => 'Proposal',       'sort_order' => 3, 'color' => 'bg-warning',   'is_protected' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => 1, 'name' => 'Negotiation',    'sort_order' => 4, 'color' => 'bg-dark',      'is_protected' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => 1, 'name' => 'Won',            'sort_order' => 5, 'color' => 'bg-success',   'is_protected' => true,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => 1, 'name' => 'Lost',           'sort_order' => 6, 'color' => 'bg-danger',    'is_protected' => true,  'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ];
            DB::table('deal_statuses')->insert($defaults);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deal_statuses');
    }
};
