<?php

namespace Database\Seeders;

use App\Domains\Platform\Models\ModulePrice;
use App\Domains\Platform\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Zoho-style plan matrix (agreed 2026-10-20): every tier is priced per user
 * per month (yearly = lower per-month price), GST added on top at checkout.
 * Starter covers the core sell/buy/stock/books loop and sells the other
 * modules as per-user add-ons; Pro has every module minus a few top-end
 * features; Enterprise has everything. Excluded features stay view-only
 * (PlanFeatureGate); limits cap resource counts (UsageLimitService,
 * config/plan_limits.php) — Enterprise has none.
 *
 * Safe to re-run — plans are matched by slug, add-ons by module. Running it
 * against a live database re-tiers existing Starter/Pro tenants, so check who
 * is on those plans first.
 *
 *   php artisan db:seed --class=PlanCatalogSeeder
 */
class PlanCatalogSeeder extends Seeder
{
    public const CORE_MODULES = ['crm', 'sales', 'inventory', 'purchase', 'accounting'];

    public const ALL_MODULES = ['crm', 'sales', 'inventory', 'purchase', 'production', 'hrms', 'accounting', 'projects', 'visitor'];

    /** Rupees per user per month: [monthly billing, yearly billing]. */
    public const ADDON_PRICES = [
        'hrms' => [199, 159],
        'projects' => [149, 119],
        'production' => [299, 239],
        'visitor' => [99, 79],
    ];

    public function run(): void
    {
        foreach ($this->plans() as $slug => $attributes) {
            Plan::query()->updateOrCreate(['slug' => $slug], $attributes + [
                'currency' => config('billing.currency', 'INR'),
                'billing_cycle' => 'monthly',
                'is_demo' => false,
                'is_active' => true,
            ]);
        }

        foreach (self::ADDON_PRICES as $module => [$monthly, $yearly]) {
            ModulePrice::query()->updateOrCreate(['module' => $module], [
                'monthly_price_per_user' => $monthly,
                'yearly_price_per_user' => $yearly,
                'is_active' => true,
            ]);
        }
    }

    /** @return array<string, array<string, mixed>> */
    private function plans(): array
    {
        return [
            'starter' => [
                'name' => 'Starter',
                'description' => 'Sell, buy, stock and books for small traders and service firms.',
                'monthly_price_per_user' => 499,
                'yearly_price_per_user' => 399,
                'min_users' => 2,
                'max_users' => 25,
                'features' => self::CORE_MODULES,
                'excluded_features' => [
                    'sales.dispatches',
                    'sales.material_requirements',
                    'inventory.warehouses',
                    'inventory.dispatches',
                    'purchase.requisitions',
                    'purchase.rfqs',
                    'purchase.advances',
                    'purchase.landed_costs',
                    'purchase.approvals',
                    'accounting.budgets',
                    'accounting.cost_centers',
                    'accounting.exchange_rates',
                    'accounting.approvals',
                    'fixed_assets',
                ],
                'limits' => ['companies' => 1, 'branches' => 1, 'warehouses' => 1, 'products' => 2000, 'customers' => 5000, 'invoices_per_month' => 500],
                'sort_order' => 1,
            ],
            'pro' => [
                'name' => 'Pro',
                'description' => 'Every module with approvals, multi-warehouse and full manufacturing for growing businesses.',
                'monthly_price_per_user' => 999,
                'yearly_price_per_user' => 799,
                'min_users' => 5,
                'max_users' => 200,
                'features' => self::ALL_MODULES,
                'excluded_features' => [
                    'accounting.exchange_rates',
                    'production.mes',
                    'production.intelligence',
                    'production.cost_adjustment',
                    'hrms.biometric_devices',
                    'hrms.feedback_360',
                    'hrms.pip',
                    'hrms.helpdesk',
                    'projects.billing',
                    'projects.changerequests',
                    'projects.reviews',
                ],
                'limits' => ['companies' => 3, 'branches' => 10, 'warehouses' => 10, 'products' => 25000, 'customers' => 50000, 'invoices_per_month' => 5000],
                'sort_order' => 2,
            ],
            'enterprise' => [
                'name' => 'Enterprise',
                'description' => 'Everything, for large multi-branch organisations.',
                'monthly_price_per_user' => 1799,
                'yearly_price_per_user' => 1499,
                'min_users' => 10,
                'max_users' => null,
                'features' => self::ALL_MODULES,
                'excluded_features' => [],
                'limits' => [],
                'sort_order' => 3,
            ],
        ];
    }
}
