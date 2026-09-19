<?php

namespace Database\Seeders;

use App\Domains\Platform\Models\PaymentTerm;
use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PaymentTermSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Payment terms belong to Sales/Purchase: only tenants whose plan includes either get them.
        $tenants = Tenant::query()->when(config('tenancy.seed_only'), fn ($q, $slugs) => $q->whereIn('slug', $slugs))->get()->filter(fn (Tenant $tenant) => $tenant->hasModule('sales', 'purchase'));

        $defaultTerms = [
            [
                'name' => 'Immediate / Due on Receipt',
                'code' => 'DUE_RECEIPT',
                'due_days' => 0,
                'description' => 'Payment due immediately upon receipt of invoice/bill.',
            ],
            [
                'name' => 'Net 15 Days',
                'code' => 'NET15',
                'due_days' => 15,
                'description' => 'Payment due within 15 calendar days.',
            ],
            [
                'name' => 'Net 30 Days',
                'code' => 'NET30',
                'due_days' => 30,
                'description' => 'Standard Net 30 days payment term.',
            ],
            [
                'name' => 'Net 45 Days',
                'code' => 'NET45',
                'due_days' => 45,
                'description' => 'Payment due within 45 calendar days.',
            ],
            [
                'name' => 'Net 60 Days',
                'code' => 'NET60',
                'due_days' => 60,
                'description' => 'Payment due within 60 calendar days.',
            ],
            [
                'name' => '50% Advance, 50% Delivery',
                'code' => 'ADV50_DEL50',
                'due_days' => 0,
                'description' => '50% payment in advance and 50% upon delivery.',
            ],
        ];

        // If no tenants in table yet, run for default tenant_id = 1
        $tenantIds = Tenant::query()->exists() ? $tenants->pluck('id')->all() : [1];

        foreach ($tenantIds as $tenantId) {
            foreach ($defaultTerms as $term) {
                PaymentTerm::firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'code' => $term['code'],
                    ],
                    [
                        'company_id' => 1,
                        'branch_id' => 1,
                        'name' => $term['name'],
                        'due_days' => $term['due_days'],
                        'discount_days' => 0,
                        'discount_percentage' => 0.00,
                        'description' => $term['description'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
