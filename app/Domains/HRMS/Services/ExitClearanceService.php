<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Models\ExitClearanceTemplate;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;

class ExitClearanceService
{
    /**
     * Fetch active templates for a given company with global fallback.
     */
    public function getActiveTemplatesForCompany(?int $companyId = null, ?int $tenantId = null): array|BaseCollection
    {
        $tenantId = $tenantId ?: tenant_id();

        if ($tenantId) {
            $tenantHasTemplates = ExitClearanceTemplate::where('tenant_id', $tenantId)->exists();
            if (!$tenantHasTemplates) {
                $this->resetTemplatesToDefaults($tenantId, $companyId);
            }
        }

        $query = ExitClearanceTemplate::query()
            ->where('status', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($companyId) {
            // First check if company-specific templates exist
            $companyTemplates = (clone $query)->where('company_id', $companyId)->orderBy('sort_order')->get();
            if ($companyTemplates->isNotEmpty()) {
                return $companyTemplates;
            }
        }

        // Check for global tenant templates (company_id is null)
        $globalTemplates = (clone $query)->whereNull('company_id')->orderBy('sort_order')->get();
        if ($globalTemplates->isNotEmpty()) {
            return $globalTemplates;
        }

        // Fallback to all active templates for this tenant if any
        $allTenantTemplates = $query->orderBy('sort_order')->get();
        if ($allTenantTemplates->isNotEmpty()) {
            return $allTenantTemplates;
        }

        // Fallback to static system default templates
        return collect(ExitClearanceTemplate::DEFAULT_TEMPLATES)->map(function ($item) {
            return (object) $item;
        });
    }

    /**
     * Fetch all templates (active & inactive) for management UI.
     */
    public function getAllTemplatesForManagement(?int $companyId = null, ?int $tenantId = null): Collection|BaseCollection
    {
        $tenantId = $tenantId ?: tenant_id();

        if ($tenantId) {
            // Auto-initialize standard default templates in database if tenant has none
            $tenantHasTemplates = ExitClearanceTemplate::where('tenant_id', $tenantId)->exists();
            if (!$tenantHasTemplates) {
                $this->resetTemplatesToDefaults($tenantId, $companyId);
            }
        }

        $query = ExitClearanceTemplate::query()->with('company');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($companyId) {
            // If company-specific templates exist, display only them; otherwise fallback to global tenant templates
            $hasCompanySpecific = ExitClearanceTemplate::where('tenant_id', $tenantId)->where('company_id', $companyId)->exists();
            if ($hasCompanySpecific) {
                $query->where('company_id', $companyId);
            } else {
                $query->whereNull('company_id');
            }
        }

        return $query->orderBy('sort_order')->get();
    }

    /**
     * Retrieve categorized metadata summary for UI cards.
     */
    public function getCategoriesSummary(?int $companyId = null, ?int $tenantId = null): BaseCollection
    {
        $templates = $this->getAllTemplatesForManagement($companyId, $tenantId);

        return $templates->groupBy('clearance_category')->map(function ($items, $categoryKey) {
            $first = $items->first();
            $categoryName = is_object($first) ? ($first->category_name ?? null) : ($first['category_name'] ?? null);
            $meta = ExitClearanceTemplate::getCategoryMetadata($categoryKey, $categoryName);

            return [
                'category_key' => $categoryKey,
                'category_name' => $meta['name'],
                'icon' => $meta['icon'],
                'color' => $meta['color'],
                'bg' => $meta['bg'],
                'total_items' => $items->count(),
                'active_items' => $items->filter(fn($i) => (bool) (is_object($i) ? $i->status : ($i['status'] ?? true)))->count(),
                'items' => $items,
            ];
        });
    }

    /**
     * Check if all department clearance items have been reviewed/completed.
     * Automatically keeps the exit workflow status synchronized between in_clearance and approved.
     */
    public function checkAllClearancesCompleted(EmployeeExit $exit): bool
    {
        $hasPending = $exit->clearances()->where('status', 'pending')->exists();

        if (!$hasPending && $exit->status === 'in_clearance') {
            $exit->update(['status' => 'approved']);
        } elseif ($hasPending && $exit->status === 'approved') {
            $exit->update(['status' => 'in_clearance']);
        }

        return !$hasPending;
    }

    /**
     * Clean and normalize department keys and remove accidental duplicates for an exit.
     */
    public function cleanDuplicateClearancesForExit(EmployeeExit $exit): void
    {
        $allClearances = EmployeeExitClearance::where('employee_exit_id', $exit->id)->get();
        $seen = [];

        foreach ($allClearances as $c) {
            $rawDept = $c->getRawOriginal('department') ?? $c->department;
            $normDept = ExitClearanceTemplate::normalizeCategoryKey($rawDept);

            if ($rawDept !== $normDept) {
                EmployeeExitClearance::where('id', $c->id)->update(['department' => $normDept]);
            }

            $key = $normDept . '::' . strtolower(trim($c->item_name));
            if (isset($seen[$key])) {
                $prev = $seen[$key];
                if ($prev->status === 'pending' && $c->status !== 'pending') {
                    EmployeeExitClearance::where('id', $prev->id)->delete();
                    $seen[$key] = $c;
                } else {
                    EmployeeExitClearance::where('id', $c->id)->delete();
                }
            } else {
                $seen[$key] = $c;
            }
        }
    }

    /**
     * Auto-generate clearance items for a newly created or approved employee exit.
     */
    public function generateClearancesForExit(EmployeeExit $exit, ?int $tenantId = null): void
    {
        $tenantId = $tenantId ?: $exit->tenant_id ?: tenant_id();
        $this->syncExitClearancesWithPolicies($exit, false);
    }

    /**
     * Synchronize an employee exit's clearance checkpoints with the active Offboarding Policies.
     */
    public function syncExitClearancesWithPolicies(EmployeeExit $exit, bool $forceCleanLegacy = false): void
    {
        $tenantId = $exit->tenant_id ?: tenant_id();
        $companyId = $exit->employee?->company_id;

        $templates = $this->getActiveTemplatesForCompany($companyId, $tenantId);
        if ($templates->isEmpty()) {
            $templates = collect(ExitClearanceTemplate::DEFAULT_TEMPLATES)->map(fn($item) => (object) $item);
        }

        $existing = EmployeeExitClearance::where('employee_exit_id', $exit->id)->get();

        $hasAllocatedAssets = \App\Domains\HRMS\Models\AssetAllocation::where('employee_id', $exit->employee_id)->whereNull('returned_at')->exists()
            || \App\Domains\HRMS\Models\Asset::where('assigned_employee_id', $exit->employee_id)->where('status', '!=', 'available')->exists();

        // Check if legacy department sign-offs exist (to preserve department approval state when syncing)
        $clearedDepts = [];
        foreach ($existing as $item) {
            $normDept = ExitClearanceTemplate::normalizeCategoryKey($item->getRawOriginal('department') ?? $item->department);
            if (in_array($item->status, ['cleared', 'waived'])) {
                $clearedDepts[$normDept] = [
                    'cleared_by' => $item->cleared_by,
                    'cleared_at' => $item->cleared_at,
                ];
            }
        }

        // Build mapping of active policy items
        $policyItemNames = [];
        $policyItems = [];
        foreach ($templates as $t) {
            $catKey = is_object($t) ? $t->clearance_category : $t['clearance_category'];
            $itemName = is_object($t) ? $t->item_name : $t['item_name'];
            $normCat = ExitClearanceTemplate::normalizeCategoryKey($catKey);
            $key = $normCat . '::' . strtolower(trim($itemName));
            $policyItemNames[$key] = true;
            $policyItems[] = [
                'department' => $normCat,
                'item_name' => $itemName,
            ];
        }

        // If forceCleanLegacy is true, clean up items that don't match any policy template
        if ($forceCleanLegacy) {
            foreach ($existing as $c) {
                $normDept = ExitClearanceTemplate::normalizeCategoryKey($c->getRawOriginal('department') ?? $c->department);
                $key = $normDept . '::' . strtolower(trim($c->item_name));

                if (!isset($policyItemNames[$key])) {
                    $c->delete();
                }
            }
            $existing = EmployeeExitClearance::where('employee_exit_id', $exit->id)->get();
        }

        // Ensure each active policy item exists in the exit's clearances
        foreach ($policyItems as $p) {
            $cat = $p['department'];
            $name = $p['item_name'];

            $matching = $existing->first(function ($c) use ($cat, $name) {
                $cDept = ExitClearanceTemplate::normalizeCategoryKey($c->getRawOriginal('department') ?? $c->department);
                return $cDept === $cat && strcasecmp(trim($c->item_name), trim($name)) === 0;
            });

            if (!$matching) {
                $isHardwarePoint = in_array($cat, ['it_assets', 'it', 'facilities_admin']) 
                    && (str_contains(strtolower($name), 'hardware') || str_contains(strtolower($name), 'laptop') || str_contains(strtolower($name), 'asset recovery'));
                $isAutoCleared = $isHardwarePoint && !$hasAllocatedAssets;

                // If department was already cleared in legacy data, preserve cleared status
                $wasDeptCleared = isset($clearedDepts[$cat]);

                EmployeeExitClearance::create([
                    'tenant_id' => $tenantId,
                    'employee_exit_id' => $exit->id,
                    'department' => $cat,
                    'item_name' => $name,
                    'status' => $wasDeptCleared ? 'cleared' : ($isAutoCleared ? 'cleared' : 'pending'),
                    'remarks' => $isAutoCleared ? 'Auto-cleared: No company physical assets assigned (BYOD/Digital Only)' : null,
                    'cleared_by' => $wasDeptCleared ? ($clearedDepts[$cat]['cleared_by'] ?? null) : null,
                    'cleared_at' => $wasDeptCleared ? ($clearedDepts[$cat]['cleared_at'] ?? now()) : ($isAutoCleared ? now() : null),
                    'deduction_amount' => 0.00,
                ]);
            }
        }

        $this->cleanDuplicateClearancesForExit($exit);
        $this->checkAllClearancesCompleted($exit);
    }

    /**
     * Synchronize all active exits (not settled, not rejected) with the latest Offboarding Policies.
     */
    public function syncAllActiveExitsWithPolicies(?int $tenantId = null, ?int $companyId = null, bool $forceCleanLegacy = false): int
    {
        $tenantId = $tenantId ?: tenant_id();
        $query = EmployeeExit::whereNotIn('status', ['settled', 'rejected']);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($companyId) {
            $query->whereHas('employee', fn($q) => $q->where('company_id', $companyId));
        }

        $exits = $query->get();
        foreach ($exits as $exit) {
            $this->syncExitClearancesWithPolicies($exit, $forceCleanLegacy);
        }

        return $exits->count();
    }

    /**
     * Add an ad-hoc employee-specific clearance item on-the-fly.
     */
    public function addAdhocClearanceItem(EmployeeExit $exit, array $data): EmployeeExitClearance
    {
        $tenantId = $exit->tenant_id ?: tenant_id();

        return EmployeeExitClearance::create([
            'tenant_id' => $tenantId,
            'employee_exit_id' => $exit->id,
            'department' => $data['clearance_category'] ?? ($data['department'] ?? 'other'),
            'item_name' => $data['item_name'],
            'status' => $data['status'] ?? 'pending',
            'remarks' => $data['remarks'] ?? null,
            'deduction_amount' => $data['deduction_amount'] ?? 0.00,
        ]);
    }

    /**
     * Seed or reset templates to the standard 5 default items for a tenant/company.
     */
    public function resetTemplatesToDefaults(int $tenantId, ?int $companyId = null): void
    {
        $query = ExitClearanceTemplate::where('tenant_id', $tenantId);
        if ($companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
        }
        $query->delete();

        foreach (ExitClearanceTemplate::DEFAULT_TEMPLATES as $item) {
            ExitClearanceTemplate::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'clearance_category' => $item['clearance_category'],
                'category_name' => $item['category_name'],
                'item_name' => $item['item_name'],
                'description' => $item['description'],
                'is_mandatory' => $item['is_mandatory'],
                'sort_order' => $item['sort_order'],
                'status' => true,
            ]);
        }
    }
}
