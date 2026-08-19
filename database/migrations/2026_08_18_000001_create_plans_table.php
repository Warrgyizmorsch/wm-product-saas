<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price')->default(0);
            $table->string('currency', 10)->default('INR');
            $table->string('billing_cycle')->default('monthly');
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_storage_mb')->nullable();
            $table->unsignedInteger('trial_days')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_demo')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('plans')->insert([
            [
                'name' => 'Demo',
                'slug' => 'demo',
                'description' => 'Time-boxed evaluation plan with tight limits, no billing required.',
                'price' => 0,
                'currency' => 'INR',
                'billing_cycle' => 'monthly',
                'max_users' => 3,
                'max_storage_mb' => 500,
                'trial_days' => 14,
                'features' => json_encode(['crm', 'inventory']),
                'is_demo' => true,
                'is_active' => true,
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Core modules for small teams getting started.',
                'price' => 0,
                'currency' => 'INR',
                'billing_cycle' => 'monthly',
                'max_users' => 5,
                'max_storage_mb' => 2048,
                'trial_days' => 14,
                'features' => json_encode(['crm', 'inventory', 'sales']),
                'is_demo' => false,
                'is_active' => true,
                'sort_order' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Full ERP coverage for growing companies.',
                'price' => 0,
                'currency' => 'INR',
                'billing_cycle' => 'monthly',
                'max_users' => 25,
                'max_storage_mb' => 10240,
                'trial_days' => 14,
                'features' => json_encode(['crm', 'inventory', 'sales', 'purchase', 'production', 'hrms']),
                'is_demo' => false,
                'is_active' => true,
                'sort_order' => 2,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Enterprise',
                'slug' => 'enterprise',
                'description' => 'Unlimited scale with every module and priority support.',
                'price' => 0,
                'currency' => 'INR',
                'billing_cycle' => 'monthly',
                'max_users' => null,
                'max_storage_mb' => null,
                'trial_days' => null,
                'features' => json_encode(['crm', 'inventory', 'sales', 'purchase', 'production', 'hrms', 'accounting', 'projects']),
                'is_demo' => false,
                'is_active' => true,
                'sort_order' => 3,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
