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
        if (!Schema::hasTable('lead_statuses')) {
            Schema::create('lead_statuses', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->default(1)->index();
                $table->string('name');
                $table->integer('sort_order')->default(0);
                $table->string('color')->default('bg-primary');
                $table->boolean('is_protected')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed default statuses for default tenant 1
            $now = now();
            $defaults = [
                ['tenant_id' => 1, 'name' => 'New',       'sort_order' => 1, 'color' => 'bg-primary',   'is_protected' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => 1, 'name' => 'Qualified', 'sort_order' => 2, 'color' => 'bg-teal',      'is_protected' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => 1, 'name' => 'Won',       'sort_order' => 3, 'color' => 'bg-success',   'is_protected' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
                ['tenant_id' => 1, 'name' => 'Lost',      'sort_order' => 4, 'color' => 'bg-danger',    'is_protected' => true, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ];
            DB::table('lead_statuses')->insert($defaults);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_statuses');
    }
};
