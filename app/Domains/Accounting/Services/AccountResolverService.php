<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Repositories\ChartOfAccountRepositoryInterface;
use App\Domains\Accounting\Support\AccountCode;
use App\Domains\Inventory\Models\Product;
use Illuminate\Support\Facades\Log;

class AccountResolverService
{
    public function __construct(
        protected ChartOfAccountRepositoryInterface $accounts
    ) {}

    /**
     * Resolves a ChartOfAccount from an ID, Code, Name, or Product property, with fallback codes/types.
     */
    public function resolveAccount(mixed $identifier, int $tenantId, ?string $fallbackCode = null, ?string $fallbackType = null): ?ChartOfAccount
    {
        if ($identifier instanceof ChartOfAccount) {
            return $identifier;
        }

        if (!empty($identifier)) {
            // 1. If numeric, try finding by ID first
            if (is_numeric($identifier)) {
                $account = ChartOfAccount::withoutGlobalScopes()
                    ->where(function ($q) use ($tenantId) {
                        $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                    })
                    ->where('id', (int)$identifier)
                    ->first();

                if ($account) {
                    return $account;
                }
            }

            // 2. Try finding by exact Code (scoped by tenant)
            $account = $this->accounts->findByCode((string)$identifier, $tenantId);
            if ($account) {
                return $account;
            }

            // 3. Try finding by Name (scoped by tenant)
            $account = ChartOfAccount::withoutGlobalScopes()
                ->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })
                ->where('name', (string)$identifier)
                ->first();

            if ($account) {
                return $account;
            }
        }

        // 4. Fallback by Code
        if ($fallbackCode !== null) {
            $fallbackAccount = $this->accounts->findByCode($fallbackCode, $tenantId);
            if ($fallbackAccount) {
                return $fallbackAccount;
            }

            // Fallback by name if code matches standard account
            $standardNames = [
                '4010' => 'Sales Revenue',
                '4030' => 'Sales Returns & Allowances',
                '5010' => 'Cost of Goods Sold',
                '5900' => 'Purchases / General Expense',
                '1200' => 'Inventory',
                '1100' => 'Accounts Receivable',
                '2010' => 'Accounts Payable',
            ];

            if (isset($standardNames[$fallbackCode])) {
                $fallbackAccount = ChartOfAccount::withoutGlobalScopes()
                    ->where(function ($q) use ($tenantId) {
                        $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                    })
                    ->where('name', 'like', '%' . $standardNames[$fallbackCode] . '%')
                    ->first();

                if ($fallbackAccount) {
                    return $fallbackAccount;
                }
            }
        }

        // 5. Fallback by Type
        if ($fallbackType !== null) {
            return ChartOfAccount::withoutGlobalScopes()
                ->where(function ($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id');
                })
                ->where('type', $fallbackType)
                ->first();
        }

        return null;
    }

    /**
     * Resolve Sales Account for a Product (or parent product), falling back to standard Sales Revenue (4010).
     */
    public function resolveSalesAccount(Product|int|null $product, int $tenantId): ?ChartOfAccount
    {
        $productModel = is_numeric($product) ? Product::find($product) : $product;
        $accountId = $productModel?->sales_account;

        if (empty($accountId) && $productModel?->parent_id) {
            $accountId = $productModel->parent?->sales_account;
        }

        return $this->resolveAccount(
            identifier: $accountId,
            tenantId: $tenantId,
            fallbackCode: '4010', // Default Sales Revenue
            fallbackType: ChartOfAccount::TYPE_INCOME
        );
    }

    /**
     * Resolve Purchase / Expense Account for a Product, falling back to Purchase Expense (5900) or COGS (5010).
     */
    public function resolvePurchaseAccount(Product|int|null $product, int $tenantId): ?ChartOfAccount
    {
        $productModel = is_numeric($product) ? Product::find($product) : $product;
        $accountId = $productModel?->purchase_account;

        if (empty($accountId) && $productModel?->parent_id) {
            $accountId = $productModel->parent?->purchase_account;
        }

        return $this->resolveAccount(
            identifier: $accountId,
            tenantId: $tenantId,
            fallbackCode: '5900', // Default Purchase Expense
            fallbackType: ChartOfAccount::TYPE_EXPENSE
        );
    }

    /**
     * Resolve Inventory Asset Account for a Product, falling back to Inventory (1200).
     */
    public function resolveInventoryAccount(Product|int|null $product, int $tenantId): ?ChartOfAccount
    {
        $productModel = is_numeric($product) ? Product::find($product) : $product;
        $accountId = $productModel?->inventory_account;

        if (empty($accountId) && $productModel?->parent_id) {
            $accountId = $productModel->parent?->inventory_account;
        }

        return $this->resolveAccount(
            identifier: $accountId,
            tenantId: $tenantId,
            fallbackCode: '1200', // Default Inventory Asset
            fallbackType: ChartOfAccount::TYPE_ASSET
        );
    }

    /**
     * Resolve COGS Account for a Product, falling back to COGS (5010).
     */
    public function resolveCogsAccount(Product|int|null $product, int $tenantId): ?ChartOfAccount
    {
        $productModel = is_numeric($product) ? Product::find($product) : $product;
        
        // If product has a purchase account that resolves to a COGS subtype or code
        if ($productModel?->purchase_account) {
            $account = $this->resolveAccount($productModel->purchase_account, $tenantId);
            if ($account && ($account->subtype === ChartOfAccount::SUBTYPE_COGS || $account->code === '5010')) {
                return $account;
            }
        }

        return $this->resolveAccount(
            identifier: null,
            tenantId: $tenantId,
            fallbackCode: '5010', // Default COGS
            fallbackType: ChartOfAccount::TYPE_EXPENSE
        );
    }
}
