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
                $this->resetTemplatesToDefaults($tenantId, null);
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
                $this->resetTemplatesToDefaults($tenantId, null);
            }
        }

        $query = ExitClearanceTemplate::query()->with('company');

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        if ($companyId) {
            $query->where(function ($q) use ($companyId) {
                $q->where('company_id', $companyId)->orWhereNull('company_id');
            });
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
        if ($exit->clearances()->count() > 0) {
            $this->cleanDuplicateClearancesForExit($exit);
            return;
        }

        $tenantId = $tenantId ?: $exit->tenant_id ?: tenant_id();
        $companyId = $exit->employee?->company_id;

        $templates = $this->getActiveTemplatesForCompany($companyId, $tenantId);

        foreach ($templates as $template) {
            $catKey = is_object($template) ? $template->clearance_category : $template['clearance_category'];
            $itemName = is_object($template) ? $template->item_name : $template['item_name'];

            EmployeeExitClearance::create([
                'tenant_id' => $tenantId,
                'employee_exit_id' => $exit->id,
                'department' => ExitClearanceTemplate::normalizeCategoryKey($catKey),
                'item_name' => $itemName,
                'status' => 'pending',
                'deduction_amount' => 0.00,
            ]);
        }
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
     * Seed or reset templates to the standard 12 default items for a tenant.
     */
    public function resetTemplatesToDefaults(int $tenantId, ?int $companyId = null): void
    {
        $query = ExitClearanceTemplate::where('tenant_id', $tenantId);
        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
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
