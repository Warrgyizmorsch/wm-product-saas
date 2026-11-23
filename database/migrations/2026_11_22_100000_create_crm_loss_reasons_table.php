<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * FRS 2.5 / C-04: Structured Loss Reasons master table.
     * Captures why a lead/deal was lost with standard reasons and custom tenant reasons.
     */
    public function up(): void
    {
        if (!Schema::hasTable('crm_loss_reasons')) {
            Schema::create('crm_loss_reasons', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->nullable()->index();
                $table->string('name', 150);
                $table->string('code', 50)->nullable()->index();
                $table->boolean('requires_competitor')->default(false);
                $table->integer('follow_up_days')->default(30);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Seed system-level default loss reasons (tenant_id = null means available system-wide)
            $defaults = [
                ['name' => 'Price / Commercial Disadvantage',       'code' => 'PRICE_TOO_HIGH',     'requires_competitor' => false, 'follow_up_days' => 30, 'sort_order' => 1, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Lost to Competitor',                    'code' => 'LOST_TO_COMPETITOR', 'requires_competitor' => true,  'follow_up_days' => 30, 'sort_order' => 2, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Delivery / Lead Time Mismatch',          'code' => 'LEAD_TIME',          'requires_competitor' => false, 'follow_up_days' => 30, 'sort_order' => 3, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Product Specification Not Met',         'code' => 'SPEC_MISMATCH',      'requires_competitor' => false, 'follow_up_days' => 30, 'sort_order' => 4, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Project Cancelled / Budget Withdrawn',   'code' => 'BUDGET_CANCELLED',   'requires_competitor' => false, 'follow_up_days' => 30, 'sort_order' => 5, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Unresponsive Prospect (No Contact)',    'code' => 'NO_RESPONSE',        'requires_competitor' => false, 'follow_up_days' => 30, 'sort_order' => 6, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
            ];

            DB::table('crm_loss_reasons')->insert($defaults);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_loss_reasons');
    }
};
