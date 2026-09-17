<?php

namespace App\Services\Search;

use App\Core\Navigation\MenuBuilder;
use App\Models\User;
use App\Services\Access\AccessService;
use Illuminate\Support\Facades\Route;

class GlobalSearchService
{
    private const MAX_TOTAL_RESULTS = 20;
    private const LIMIT_ALL_PER_ENTITY = 3;
    private const LIMIT_MODULE_PER_ENTITY = 8;

    public function __construct(
        private readonly MenuBuilder $menuBuilder,
        private readonly AccessService $accessService,
    ) {
    }

    /**
     * @return array{
     *     query: string,
     *     module: string,
     *     total: int,
     *     results: list<array{
     *         category: string,
     *         title: string,
     *         subtitle: string,
     *         url: string,
     *         icon: string
     *     }>
     * }
     */
    public function search(?User $user, string $rawQuery, string $module = 'all'): array
    {
        $query = trim($rawQuery);
        $module = strtolower(trim($module));
        $validModules = ['all', 'navigation', 'production', 'inventory', 'sales', 'purchase', 'accounting', 'hrms', 'projects', 'crm'];

        if (! in_array($module, $validModules, true)) {
            $module = 'all';
        }

        if (mb_strlen($query) < 2 || $user === null) {
            return [
                'query' => $query,
                'module' => $module,
                'total' => 0,
                'results' => [],
            ];
        }

        $limitPerEntity = ($module === 'all') ? self::LIMIT_ALL_PER_ENTITY : self::LIMIT_MODULE_PER_ENTITY;
        $results = [];

        // 1. Navigation / Menu search
        $navResults = $this->searchNavigation($user, $query, $module);
        $results = array_merge($results, $navResults);

        if (count($results) >= self::MAX_TOTAL_RESULTS) {
            return $this->formatResponse($query, $module, array_slice($results, 0, self::MAX_TOTAL_RESULTS));
        }

        // 2. Production Domain
        if (($module === 'all' || $module === 'production') && $this->canAccessModule($user, 'production')) {
            $results = array_merge($results, $this->searchProduction($user, $query, $limitPerEntity));
        }

        if (count($results) >= self::MAX_TOTAL_RESULTS) {
            return $this->formatResponse($query, $module, array_slice($results, 0, self::MAX_TOTAL_RESULTS));
        }

        // 3. Inventory Domain
        if (($module === 'all' || $module === 'inventory') && $this->canAccessModule($user, 'inventory')) {
            $results = array_merge($results, $this->searchInventory($user, $query, $limitPerEntity));
        }

        if (count($results) >= self::MAX_TOTAL_RESULTS) {
            return $this->formatResponse($query, $module, array_slice($results, 0, self::MAX_TOTAL_RESULTS));
        }

        // 4. CRM Domain
        if (($module === 'all' || $module === 'crm') && $this->canAccessModule($user, 'crm')) {
            $results = array_merge($results, $this->searchCrm($user, $query, $limitPerEntity));
        }

        if (count($results) >= self::MAX_TOTAL_RESULTS) {
            return $this->formatResponse($query, $module, array_slice($results, 0, self::MAX_TOTAL_RESULTS));
        }

        // 5. Sales Domain
        if (($module === 'all' || $module === 'sales') && $this->canAccessModule($user, 'sales')) {
            $results = array_merge($results, $this->searchSales($user, $query, $limitPerEntity));
        }

        if (count($results) >= self::MAX_TOTAL_RESULTS) {
            return $this->formatResponse($query, $module, array_slice($results, 0, self::MAX_TOTAL_RESULTS));
        }

        // 6. Purchase Domain
        if (($module === 'all' || $module === 'purchase') && $this->canAccessModule($user, 'purchase')) {
            $results = array_merge($results, $this->searchPurchase($user, $query, $limitPerEntity));
        }

        if (count($results) >= self::MAX_TOTAL_RESULTS) {
            return $this->formatResponse($query, $module, array_slice($results, 0, self::MAX_TOTAL_RESULTS));
        }

        // 7. Accounting Domain
        if (($module === 'all' || $module === 'accounting') && $this->canAccessModule($user, 'accounting')) {
            $results = array_merge($results, $this->searchAccounting($user, $query, $limitPerEntity));
        }

        if (count($results) >= self::MAX_TOTAL_RESULTS) {
            return $this->formatResponse($query, $module, array_slice($results, 0, self::MAX_TOTAL_RESULTS));
        }

        // 8. HRMS Domain
        if (($module === 'all' || $module === 'hrms') && $this->canAccessModule($user, 'hrms')) {
            $results = array_merge($results, $this->searchHrms($user, $query, $limitPerEntity));
        }

        if (count($results) >= self::MAX_TOTAL_RESULTS) {
            return $this->formatResponse($query, $module, array_slice($results, 0, self::MAX_TOTAL_RESULTS));
        }

        // 9. Projects Domain
        if (($module === 'all' || $module === 'projects') && $this->canAccessModule($user, 'projects')) {
            $results = array_merge($results, $this->searchProjects($user, $query, $limitPerEntity));
        }

        return $this->formatResponse($query, $module, array_slice($results, 0, self::MAX_TOTAL_RESULTS));
    }

    /**
     * Check if a module is allowed by tenant subscription plan AND permitted for the user's roles.
     */
    private function canAccessModule(User $user, string $module): bool
    {
        $planModules = tenant_allowed_modules();
        if ($planModules !== null && ! in_array('*', $planModules, true) && ! in_array($module, $planModules, true)) {
            return false;
        }

        $roleModules = $this->accessService->allowedModulesFor($user);
        if ($roleModules !== null && ! in_array($module, $roleModules, true)) {
            return false;
        }

        return true;
    }

    /**
     * Check if user holds any of the given entity permissions.
     *
     * @param list<string> $permissions
     */
    private function hasAnyPermission(User $user, array $permissions): bool
    {
        if ($permissions === []) {
            return true;
        }

        $context = [
            'tenant_id' => $user->tenant_id,
            'owner_id' => $user->id,
            'branch_id' => $user->branch_id,
            'department_id' => $user->department_id,
            'company_id' => $user->company_id,
        ];

        foreach ($permissions as $permission) {
            if ($this->accessService->allows($user, $permission, $context)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Search Navigation / Menu items already authorized for the current user.
     *
     * @return list<array{category: string, title: string, subtitle: string, url: string, icon: string}>
     */
    private function searchNavigation(User $user, string $query, string $module = 'all'): array
    {
        $sections = $this->menuBuilder->build($user);
        $matched = [];
        $lowerQuery = mb_strtolower($query);

        $moduleSectionMap = [
            'production' => ['production'],
            'sales' => ['revenue_cycle'],
            'crm' => ['revenue_cycle'],
            'purchase' => ['supply_chain'],
            'inventory' => ['supply_chain'],
            'accounting' => ['finance'],
            'hrms' => ['hrms'],
            'projects' => ['workspace'],
        ];

        $targetSections = $moduleSectionMap[$module] ?? null;
        $limit = ($module === 'all') ? 8 : (($module === 'navigation') ? 15 : 6);
        $perSectionLimit = ($module === 'all') ? 2 : $limit;

        foreach ($sections as $section) {
            $sectionKey = $section['key'] ?? '';
            if ($targetSections !== null && ! in_array($sectionKey, $targetSections, true)) {
                continue;
            }

            $sectionLabel = $section['label'] ?? '';
            $sectionMatched = [];
            $this->collectMatchingNavItems($section['items'] ?? [], $sectionLabel, $lowerQuery, $sectionMatched, $perSectionLimit, $module);
            $matched = array_merge($matched, $sectionMatched);

            if (count($matched) >= $limit) {
                break;
            }
        }

        return array_slice($matched, 0, $limit);
    }

    private function collectMatchingNavItems(
        array $items,
        string $trail,
        string $lowerQuery,
        array &$matched,
        int $limit,
        string $module = 'all'
    ): void {
        foreach ($items as $item) {
            if (count($matched) >= $limit) {
                return;
            }

            $label = is_string($item['label'] ?? null) ? $item['label'] : '';
            $currentTrail = $trail !== '' ? "{$trail} > {$label}" : $label;
            $url = $item['url'] ?? '';

            // If a specific sub-module is requested within a shared section, ensure the item matches that domain
            if ($module === 'sales' && ! str_contains(mb_strtolower($currentTrail . ' ' . $url), 'sale') && ! str_contains($url, '/sales/')) {
                continue;
            }
            if ($module === 'crm' && ! str_contains(mb_strtolower($currentTrail . ' ' . $url), 'crm') && ! str_contains($url, '/crm/') && ! str_contains(mb_strtolower($currentTrail), 'lead') && ! str_contains(mb_strtolower($currentTrail), 'customer')) {
                continue;
            }
            if ($module === 'purchase' && ! str_contains(mb_strtolower($currentTrail . ' ' . $url), 'purchase') && ! str_contains($url, '/purchase/') && ! str_contains(mb_strtolower($currentTrail), 'vendor')) {
                continue;
            }
            if ($module === 'inventory' && ! str_contains(mb_strtolower($currentTrail . ' ' . $url), 'inventory') && ! str_contains(mb_strtolower($currentTrail), 'store') && ! str_contains($url, '/inventory/') && ! str_contains(mb_strtolower($currentTrail), 'warehouse')) {
                continue;
            }

            $matches = str_contains(mb_strtolower($label), $lowerQuery) || str_contains(mb_strtolower($trail), $lowerQuery);

            if ($matches && ! empty($url) && $url !== '#') {
                $matched[] = [
                    'category' => 'Navigation',
                    'title' => $label,
                    'subtitle' => $currentTrail,
                    'url' => $url,
                    'icon' => $item['icon'] ?? 'feather-compass',
                ];
            }

            if (! empty($item['children'])) {
                $this->collectMatchingNavItems($item['children'], $currentTrail, $lowerQuery, $matched, $limit, $module);
            }
        }
    }

    /**
     * Search Production Domain
     *
     * @return list<array{category: string, title: string, subtitle: string, url: string, icon: string}>
     */
    private function searchProduction(User $user, string $query, int $limit): array
    {
        $results = [];

        // Production Orders
        if ($this->hasAnyPermission($user, ['production.order.create', 'production.order.update', 'production.intelligence.view', 'production.mes.execute'])) {
            $orders = \App\Domains\Production\Models\ProductionOrder::query()
                ->select(['id', 'order_number', 'status'])
                ->where('order_number', 'like', "{$query}%")
                ->limit($limit)
                ->get();

            foreach ($orders as $order) {
                $results[] = [
                    'category' => 'Production Orders',
                    'title' => (string) $order->order_number,
                    'subtitle' => 'Status: ' . ucfirst((string) $order->status),
                    'url' => route('production.orders.show', $order->id),
                    'icon' => 'feather-play-circle',
                ];
            }
        }

        // BOMs
        if ($this->hasAnyPermission($user, ['production.bom.create', 'production.bom.update', 'production.intelligence.view'])) {
            $boms = \App\Domains\Production\Models\ProductionBom::query()
                ->select(['id', 'bom_number', 'bom_name', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('bom_number', 'like', "{$query}%")
                      ->orWhere('bom_name', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($boms as $bom) {
                $results[] = [
                    'category' => 'Bill of Materials',
                    'title' => (string) $bom->bom_number,
                    'subtitle' => (string) ($bom->bom_name ?: 'BOM') . ' • ' . ucfirst((string) $bom->status),
                    'url' => route('production.boms.show', $bom->id),
                    'icon' => 'feather-layers',
                ];
            }
        }

        // Routings
        if ($this->hasAnyPermission($user, ['production.routing.create', 'production.routing.update', 'production.intelligence.view'])) {
            $routings = \App\Domains\Production\Models\Routing::query()
                ->select(['id', 'routing_number', 'name', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('routing_number', 'like', "{$query}%")
                      ->orWhere('name', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($routings as $routing) {
                $results[] = [
                    'category' => 'Routings',
                    'title' => (string) ($routing->name ?: $routing->routing_number),
                    'subtitle' => 'Routing: ' . $routing->routing_number . ' • ' . ucfirst((string) $routing->status),
                    'url' => route('production.routing.show', $routing->id),
                    'icon' => 'feather-git-commit',
                ];
            }
        }

        // Work Centers
        if ($this->hasAnyPermission($user, ['production.work_center.manage', 'production.intelligence.view', 'production.mes.execute'])) {
            $workCenters = \App\Domains\Production\Models\WorkCenter::query()
                ->select(['id', 'code', 'name', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('code', 'like', "{$query}%")
                      ->orWhere('name', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($workCenters as $wc) {
                $results[] = [
                    'category' => 'Work Centers',
                    'title' => (string) ($wc->name ?: $wc->code),
                    'subtitle' => 'Code: ' . $wc->code . ' • ' . ucfirst((string) $wc->status),
                    'url' => route('production.work-centers.show', $wc->id),
                    'icon' => 'feather-cpu',
                ];
            }
        }

        // Machines
        if ($this->hasAnyPermission($user, ['production.machine.manage', 'production.intelligence.view', 'production.mes.execute'])) {
            $machines = \App\Domains\Production\Models\Machine::query()
                ->select(['id', 'code', 'name', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('code', 'like', "{$query}%")
                      ->orWhere('name', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($machines as $machine) {
                $results[] = [
                    'category' => 'Machines',
                    'title' => (string) ($machine->name ?: $machine->code),
                    'subtitle' => 'Code: ' . $machine->code . ' • ' . ucfirst((string) $machine->status),
                    'url' => route('production.machines.show', $machine->id),
                    'icon' => 'feather-server',
                ];
            }
        }

        return $results;
    }

    /**
     * Search Inventory Domain
     *
     * @return list<array{category: string, title: string, subtitle: string, url: string, icon: string}>
     */
    private function searchInventory(User $user, string $query, int $limit): array
    {
        $results = [];

        // Products
        if ($this->hasAnyPermission($user, ['inventory.products.view', 'inventory.products.create'])) {
            $products = \App\Domains\Inventory\Models\Product::query()
                ->select(['id', 'sku', 'name', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('sku', 'like', "{$query}%")
                      ->orWhere('name', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($products as $product) {
                $results[] = [
                    'category' => 'Products',
                    'title' => (string) $product->name,
                    'subtitle' => 'SKU: ' . $product->sku . ' • ' . ucfirst((string) $product->status),
                    'url' => route('inventory.products.show', $product->id),
                    'icon' => 'feather-box',
                ];
            }
        }

        // Warehouses
        if ($this->hasAnyPermission($user, ['inventory.warehouses.manage'])) {
            $warehouses = \App\Domains\Inventory\Models\Warehouse::query()
                ->select(['id', 'code', 'name', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('code', 'like', "{$query}%")
                      ->orWhere('name', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($warehouses as $wh) {
                $results[] = [
                    'category' => 'Warehouses',
                    'title' => (string) $wh->name,
                    'subtitle' => 'Code: ' . ($wh->code ?: 'N/A') . ' • ' . ucfirst((string) $wh->status),
                    'url' => route('inventory.warehouses.index'),
                    'icon' => 'feather-home',
                ];
            }
        }

        // Stock Transfers
        if ($this->hasAnyPermission($user, ['inventory.material_requirements.view', 'inventory.dispatches.view'])) {
            $transfers = \App\Domains\Inventory\Models\StockTransfer::query()
                ->select(['id', 'transfer_number', 'status'])
                ->where('transfer_number', 'like', "{$query}%")
                ->limit($limit)
                ->get();

            foreach ($transfers as $st) {
                $results[] = [
                    'category' => 'Stock Transfers',
                    'title' => (string) $st->transfer_number,
                    'subtitle' => 'Status: ' . ucfirst((string) $st->status),
                    'url' => route('inventory.transfers.show', $st->id),
                    'icon' => 'feather-repeat',
                ];
            }
        }

        return $results;
    }

    /**
     * Search CRM Domain
     *
     * @return list<array{category: string, title: string, subtitle: string, url: string, icon: string}>
     */
    private function searchCrm(User $user, string $query, int $limit): array
    {
        $results = [];

        // Customers
        if ($this->hasAnyPermission($user, ['crm.customers.view', 'crm.customers.create'])) {
            $customers = \App\Domains\CRM\Models\Customer::query()
                ->select(['id', 'name', 'company_name', 'email', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                      ->orWhere('company_name', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($customers as $customer) {
                $results[] = [
                    'category' => 'Customers',
                    'title' => (string) ($customer->company_name ?: $customer->name),
                    'subtitle' => ($customer->name && $customer->name !== $customer->company_name ? $customer->name . ' • ' : '') . ($customer->email ?: ucfirst((string) $customer->status)),
                    'url' => route('crm.customers.show', $customer->id),
                    'icon' => 'feather-users',
                ];
            }
        }

        // Leads
        if ($this->hasAnyPermission($user, ['crm.leads.view', 'crm.leads.create'])) {
            $leads = \App\Domains\CRM\Models\Lead::query()
                ->select(['id', 'lead_number', 'company_name', 'contact_person', 'email', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('lead_number', 'like', "{$query}%")
                      ->orWhere('company_name', 'like', "%{$query}%")
                      ->orWhere('contact_person', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($leads as $lead) {
                $results[] = [
                    'category' => 'Leads',
                    'title' => (string) ($lead->company_name ?: $lead->contact_person ?: $lead->lead_number),
                    'subtitle' => ($lead->lead_number ? $lead->lead_number . ' • ' : '') . ucfirst((string) $lead->status),
                    'url' => route('crm.leads.show', $lead->id),
                    'icon' => 'feather-user-check',
                ];
            }
        }

        // Deals
        if ($this->hasAnyPermission($user, ['crm.deals.view', 'crm.leads.view'])) {
            $deals = \App\Domains\CRM\Models\CrmDeal::query()
                ->select(['id', 'deal_number', 'title', 'stage'])
                ->where(function ($q) use ($query) {
                    $q->where('deal_number', 'like', "{$query}%")
                      ->orWhere('title', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($deals as $deal) {
                $results[] = [
                    'category' => 'Deals',
                    'title' => (string) ($deal->title ?: $deal->deal_number),
                    'subtitle' => ($deal->deal_number ? $deal->deal_number . ' • ' : '') . ucfirst((string) $deal->stage),
                    'url' => route('crm.deals.show', $deal->id),
                    'icon' => 'feather-target',
                ];
            }
        }

        // Quotations
        if ($this->hasAnyPermission($user, ['crm.quotations.view', 'crm.quotations.create'])) {
            $quotations = \App\Domains\CRM\Models\Quotation::query()
                ->select(['id', 'quotation_number', 'total_amount', 'status'])
                ->where('quotation_number', 'like', "{$query}%")
                ->limit($limit)
                ->get();

            foreach ($quotations as $quotation) {
                $results[] = [
                    'category' => 'Quotations',
                    'title' => (string) $quotation->quotation_number,
                    'subtitle' => 'Status: ' . ucfirst((string) $quotation->status) . ($quotation->total_amount ? ' • ' . number_format((float) $quotation->total_amount, 2) : ''),
                    'url' => route('crm.quotations.show', $quotation->id),
                    'icon' => 'feather-file-text',
                ];
            }
        }

        return $results;
    }

    /**
     * Search Sales Domain
     *
     * @return list<array{category: string, title: string, subtitle: string, url: string, icon: string}>
     */
    private function searchSales(User $user, string $query, int $limit): array
    {
        $results = [];

        // Sales Orders
        if ($this->hasAnyPermission($user, ['sales.orders.view', 'sales.orders.create'])) {
            $salesOrders = \App\Domains\Sales\Models\SalesOrder::query()
                ->select(['id', 'sales_order_number', 'status', 'total_amount'])
                ->where('sales_order_number', 'like', "{$query}%")
                ->limit($limit)
                ->get();

            foreach ($salesOrders as $so) {
                $results[] = [
                    'category' => 'Sales Orders',
                    'title' => (string) $so->sales_order_number,
                    'subtitle' => 'Status: ' . ucfirst((string) $so->status) . ($so->total_amount ? ' • ' . number_format((float) $so->total_amount, 2) : ''),
                    'url' => route('sales.orders.show', $so->id),
                    'icon' => 'feather-shopping-cart',
                ];
            }
        }

        // Invoices
        if ($this->hasAnyPermission($user, ['sales.invoices.view', 'sales.invoices.create'])) {
            $invoices = \App\Domains\Sales\Models\Invoice::query()
                ->select(['id', 'invoice_number', 'status', 'total_amount'])
                ->where('invoice_number', 'like', "{$query}%")
                ->limit($limit)
                ->get();

            foreach ($invoices as $inv) {
                $results[] = [
                    'category' => 'Invoices',
                    'title' => (string) $inv->invoice_number,
                    'subtitle' => 'Status: ' . ucfirst((string) $inv->status) . ($inv->total_amount ? ' • ' . number_format((float) $inv->total_amount, 2) : ''),
                    'url' => route('sales.invoices.show', $inv->id),
                    'icon' => 'feather-dollar-sign',
                ];
            }
        }

        return $results;
    }

    /**
     * Search Purchase Domain
     *
     * @return list<array{category: string, title: string, subtitle: string, url: string, icon: string}>
     */
    private function searchPurchase(User $user, string $query, int $limit): array
    {
        $results = [];

        // Purchase Orders
        if ($this->hasAnyPermission($user, ['purchase.orders.view', 'purchase.orders.create'])) {
            $pos = \App\Domains\Purchase\Models\PurchaseOrder::query()
                ->select(['id', 'purchase_order_number', 'status', 'grand_total'])
                ->where('purchase_order_number', 'like', "{$query}%")
                ->limit($limit)
                ->get();

            foreach ($pos as $po) {
                $results[] = [
                    'category' => 'Purchase Orders',
                    'title' => (string) $po->purchase_order_number,
                    'subtitle' => 'Status: ' . ucfirst((string) $po->status) . ($po->grand_total ? ' • ' . number_format((float) $po->grand_total, 2) : ''),
                    'url' => route('purchase.orders.show', $po->id),
                    'icon' => 'feather-truck',
                ];
            }
        }

        // Purchase RFQs
        if ($this->hasAnyPermission($user, ['purchase.rfqs.view', 'purchase.rfqs.create'])) {
            $rfqs = \App\Domains\Purchase\Models\PurchaseRfq::query()
                ->select(['id', 'rfq_number', 'status'])
                ->where('rfq_number', 'like', "{$query}%")
                ->limit($limit)
                ->get();

            foreach ($rfqs as $rfq) {
                $results[] = [
                    'category' => 'Purchase RFQs',
                    'title' => (string) $rfq->rfq_number,
                    'subtitle' => 'Status: ' . ucfirst((string) $rfq->status),
                    'url' => route('purchase.rfqs.show', $rfq->id),
                    'icon' => 'feather-clipboard',
                ];
            }
        }

        // Vendor Bills
        if ($this->hasAnyPermission($user, ['purchase.bills.view', 'purchase.bills.create'])) {
            $bills = \App\Domains\Purchase\Models\VendorBill::query()
                ->select(['id', 'bill_number', 'vendor_invoice_number', 'status', 'grand_total'])
                ->where(function ($q) use ($query) {
                    $q->where('bill_number', 'like', "{$query}%")
                      ->orWhere('vendor_invoice_number', 'like', "{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($bills as $bill) {
                $results[] = [
                    'category' => 'Vendor Bills',
                    'title' => (string) $bill->bill_number,
                    'subtitle' => 'Status: ' . ucfirst((string) $bill->status) . ($bill->grand_total ? ' • ' . number_format((float) $bill->grand_total, 2) : ''),
                    'url' => route('purchase.bills.show', $bill->id),
                    'icon' => 'feather-file-text',
                ];
            }
        }

        // Vendors
        if ($this->hasAnyPermission($user, ['purchase.vendors.view', 'purchase.vendors.create'])) {
            $vendors = \App\Domains\Inventory\Models\Vendor::query()
                ->select(['id', 'name', 'company_name', 'code', 'email', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('code', 'like', "{$query}%")
                      ->orWhere('name', 'like', "%{$query}%")
                      ->orWhere('company_name', 'like', "%{$query}%")
                      ->orWhere('email', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($vendors as $vendor) {
                $results[] = [
                    'category' => 'Vendors',
                    'title' => (string) ($vendor->name ?: $vendor->company_name ?: $vendor->code),
                    'subtitle' => ($vendor->code ? 'Code: ' . $vendor->code . ' • ' : '') . ($vendor->email ?: ucfirst((string) $vendor->status)),
                    'url' => route('purchase.vendors.show', $vendor->id),
                    'icon' => 'feather-briefcase',
                ];
            }
        }

        return $results;
    }

    /**
     * Search Accounting Domain
     *
     * @return list<array{category: string, title: string, subtitle: string, url: string, icon: string}>
     */
    private function searchAccounting(User $user, string $query, int $limit): array
    {
        $results = [];

        // Chart of Accounts
        if ($this->hasAnyPermission($user, ['accounting.chart_of_accounts.view', 'accounting.chart_of_accounts.create'])) {
            $accounts = \App\Domains\Accounting\Models\ChartOfAccount::query()
                ->select(['id', 'code', 'name', 'type', 'is_active'])
                ->where(function ($q) use ($query) {
                    $q->where('code', 'like', "{$query}%")
                      ->orWhere('name', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($accounts as $acc) {
                $results[] = [
                    'category' => 'Chart of Accounts',
                    'title' => (string) $acc->name,
                    'subtitle' => 'Code: ' . $acc->code . ' • Type: ' . ucfirst((string) $acc->type),
                    'url' => route('accounting.chart-of-accounts.index'),
                    'icon' => 'feather-book-open',
                ];
            }
        }

        // Journals
        if ($this->hasAnyPermission($user, ['accounting.journals.view', 'accounting.journals.post'])) {
            $journals = \App\Domains\Accounting\Models\Journal::query()
                ->select(['id', 'journal_number', 'reference_type', 'status'])
                ->where('journal_number', 'like', "{$query}%")
                ->limit($limit)
                ->get();

            foreach ($journals as $journal) {
                $results[] = [
                    'category' => 'Journals',
                    'title' => (string) $journal->journal_number,
                    'subtitle' => ($journal->reference_type ? 'Type: ' . $journal->reference_type . ' • ' : '') . 'Status: ' . ucfirst((string) $journal->status),
                    'url' => route('accounting.journals.show', $journal->id),
                    'icon' => 'feather-file',
                ];
            }
        }

        return $results;
    }

    /**
     * Search HRMS Domain
     *
     * @return list<array{category: string, title: string, subtitle: string, url: string, icon: string}>
     */
    private function searchHrms(User $user, string $query, int $limit): array
    {
        $results = [];

        // Employees
        if ($this->hasAnyPermission($user, ['hrms.employees.view', 'hrms.employees.create', 'hr.employees.manage'])) {
            $employees = \App\Domains\HRMS\Models\Employee::query()
                ->select(['id', 'employee_id', 'full_name', 'job_title', 'office_email', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('employee_id', 'like', "{$query}%")
                      ->orWhere('full_name', 'like', "%{$query}%")
                      ->orWhere('office_email', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($employees as $emp) {
                $results[] = [
                    'category' => 'Employees',
                    'title' => (string) ($emp->full_name ?: $emp->employee_id),
                    'subtitle' => 'ID: ' . $emp->employee_id . ($emp->job_title ? ' • ' . $emp->job_title : '') . ($emp->office_email ? ' • ' . $emp->office_email : ''),
                    'url' => route('hrms.employees.show', $emp->id),
                    'icon' => 'feather-user',
                ];
            }
        }

        return $results;
    }

    /**
     * Search Projects Domain
     *
     * @return list<array{category: string, title: string, subtitle: string, url: string, icon: string}>
     */
    private function searchProjects(User $user, string $query, int $limit): array
    {
        $results = [];

        // Projects
        if ($this->hasAnyPermission($user, ['projects.projects.view', 'projects.view'])) {
            $projects = \App\Domains\Projects\Models\Project::query()
                ->select(['id', 'project_code', 'name', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('project_code', 'like', "{$query}%")
                      ->orWhere('name', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($projects as $project) {
                $results[] = [
                    'category' => 'Projects',
                    'title' => (string) $project->name,
                    'subtitle' => 'Code: ' . ($project->project_code ?: 'N/A') . ' • ' . ucfirst((string) $project->status),
                    'url' => route('projects.show', $project->id),
                    'icon' => 'feather-briefcase',
                ];
            }
        }

        // Tasks
        if ($this->hasAnyPermission($user, ['projects.projects.view', 'projects.tasks.view'])) {
            $tasks = \App\Domains\Projects\Models\Task::query()
                ->select(['id', 'task_code', 'title', 'status'])
                ->where(function ($q) use ($query) {
                    $q->where('task_code', 'like', "{$query}%")
                      ->orWhere('title', 'like', "%{$query}%");
                })
                ->limit($limit)
                ->get();

            foreach ($tasks as $task) {
                $results[] = [
                    'category' => 'Tasks',
                    'title' => (string) $task->title,
                    'subtitle' => ($task->task_code ? 'Code: ' . $task->task_code . ' • ' : '') . ucfirst((string) $task->status),
                    'url' => route('projects.tasks.show', $task->id),
                    'icon' => 'feather-check-square',
                ];
            }
        }

        return $results;
    }

    /**
     * @param list<array{category: string, title: string, subtitle: string, url: string, icon: string}> $results
     * @return array{query: string, module: string, total: int, results: list<array{category: string, title: string, subtitle: string, url: string, icon: string}>}
     */
    private function formatResponse(string $query, string $module, array $results): array
    {
        return [
            'query' => $query,
            'module' => $module,
            'total' => count($results),
            'results' => $results,
        ];
    }
}
