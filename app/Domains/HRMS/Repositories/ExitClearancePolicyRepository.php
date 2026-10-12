<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\ExitClearanceTemplate;
use App\Domains\HRMS\Services\ExitClearanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExitClearancePolicyRepository implements ExitClearancePolicyRepositoryInterface
{
    public function __construct(
        private readonly ExitClearanceService $clearanceService
    ) {
    }

    public function getIndexData(array $inputs): array
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $selectedCompanyId = $inputs['company_id'] ?? null;
        $selectedCategory = $inputs['category'] ?? null;
        $selectedStatus = $inputs['status'] ?? null;
        $selectedMandatory = $inputs['is_mandatory'] ?? null;
        $search = $inputs['search'] ?? null;

        $companies = Company::orderBy('company_name')->get();
        $allTemplates = $this->clearanceService->getAllTemplatesForManagement(
            $selectedCompanyId ? (int) $selectedCompanyId : null,
            $tenantId
        );

        // Apply filters on the templates collection
        $filteredTemplates = $allTemplates->filter(function ($t) use ($search, $selectedCategory, $selectedStatus, $selectedMandatory) {
            $name = is_object($t) ? $t->item_name : ($t['item_name'] ?? '');
            $desc = is_object($t) ? ($t->description ?? '') : ($t['description'] ?? '');
            $cat = is_object($t) ? $t->clearance_category : ($t['clearance_category'] ?? '');
            $status = (bool) (is_object($t) ? $t->status : ($t['status'] ?? true));
            $isMandatory = (bool) (is_object($t) ? $t->is_mandatory : ($t['is_mandatory'] ?? true));

            if ($search) {
                $q = strtolower($search);
                if (!str_contains(strtolower($name), $q) && !str_contains(strtolower($desc), $q)) {
                    return false;
                }
            }

            if ($selectedCategory && $cat !== $selectedCategory) {
                return false;
            }

            if ($selectedStatus !== null && $selectedStatus !== '') {
                $expectedStatus = in_array((string)$selectedStatus, ['1', 'true', 'active'], true);
                if ($status !== $expectedStatus) {
                    return false;
                }
            }

            if ($selectedMandatory !== null && $selectedMandatory !== '') {
                $expectedMandatory = in_array((string)$selectedMandatory, ['1', 'true', 'mandatory'], true);
                if ($isMandatory !== $expectedMandatory) {
                    return false;
                }
            }

            return true;
        });

        // Group filtered templates by category
        $clearanceCategories = $filteredTemplates->groupBy('clearance_category')->map(function ($items, $categoryKey) {
            $first = $items->first();
            $categoryName = is_object($first) ? ($first->category_name ?? null) : ($first['category_name'] ?? null);
            $meta = ExitClearanceTemplate::getCategoryMetadata($categoryKey, $categoryName);

            return [
                'category_key'   => $categoryKey,
                'category_name'  => $meta['name'] ?? $meta['title'] ?? $categoryName ?? ucwords(str_replace(['_', '-'], ' ', $categoryKey)),
                'color'          => $meta['color'] ?? 'primary',
                'icon'           => $meta['icon'] ?? 'feather-check-circle',
                'items'          => $items,
                'total_items'    => $items->count(),
                'active_items'   => $items->filter(fn($i) => (bool) (is_object($i) ? $i->status : ($i['status'] ?? true)))->count(),
            ];
        });

        $defaultCategories = [
            'it_assets'          => __('hrms.offboarding_policies.dept_it_assets'),
            'facilities_admin'   => __('hrms.offboarding_policies.dept_facilities_admin'),
            'finance_payroll'    => __('hrms.offboarding_policies.dept_finance_payroll'),
            'hr_operations'      => __('hrms.offboarding_policies.dept_hr_operations'),
            'reporting_manager'  => __('hrms.offboarding_policies.dept_reporting_manager'),
            'legal_compliance'   => __('hrms.offboarding_policies.dept_legal_compliance'),
            'other'              => __('hrms.offboarding_policies.dept_other'),
        ];

        $templateCategories = $allTemplates->mapWithKeys(function ($item) {
            $key = is_object($item) ? $item->clearance_category : ($item['clearance_category'] ?? '');
            $name = is_object($item) ? ($item->category_name ?? $key) : ($item['category_name'] ?? $key);
            $meta = ExitClearanceTemplate::getCategoryMetadata($key, $name);
            return [$key => $meta['name'] ?? $meta['title'] ?? $name];
        })->filter();

        $availableCategories = $templateCategories->merge($defaultCategories);

        $sortBy = $inputs['sort_by'] ?? 'order';
        $sortOrder = in_array(strtolower($inputs['sort_order'] ?? 'asc'), ['asc', 'desc']) ? strtolower($inputs['sort_order'] ?? 'asc') : 'asc';

        // Apply sorting on the filtered templates collection
        if ($sortBy === 'name') {
            $filteredTemplates = $sortOrder === 'asc' 
                ? $filteredTemplates->sortBy(fn($t) => strtolower(is_object($t) ? $t->item_name : ($t['item_name'] ?? '')))
                : $filteredTemplates->sortByDesc(fn($t) => strtolower(is_object($t) ? $t->item_name : ($t['item_name'] ?? '')));
        } elseif ($sortBy === 'category') {
            $filteredTemplates = $sortOrder === 'asc'
                ? $filteredTemplates->sortBy(fn($t) => strtolower(is_object($t) ? $t->clearance_category : ($t['clearance_category'] ?? '')))
                : $filteredTemplates->sortByDesc(fn($t) => strtolower(is_object($t) ? $t->clearance_category : ($t['clearance_category'] ?? '')));
        } elseif ($sortBy === 'mandatory') {
            $filteredTemplates = $sortOrder === 'asc'
                ? $filteredTemplates->sortBy(fn($t) => (bool)(is_object($t) ? $t->is_mandatory : ($t['is_mandatory'] ?? true)))
                : $filteredTemplates->sortByDesc(fn($t) => (bool)(is_object($t) ? $t->is_mandatory : ($t['is_mandatory'] ?? true)));
        } else {
            // Default: sort order
            $filteredTemplates = $sortOrder === 'asc'
                ? $filteredTemplates->sortBy(fn($t) => (int)(is_object($t) ? $t->sort_order : ($t['sort_order'] ?? 0)))
                : $filteredTemplates->sortByDesc(fn($t) => (int)(is_object($t) ? $t->sort_order : ($t['sort_order'] ?? 0)));
        }

        $hasActiveFilters = !empty($search) || !empty($selectedCategory) || $selectedStatus !== null || $selectedMandatory !== null || !empty($selectedCompanyId);

        $totalPointsCount = $allTemplates->count();
        $mandatoryPointsCount = $allTemplates->filter(fn($t) => (bool)(is_object($t) ? $t->is_mandatory : ($t['is_mandatory'] ?? true)))->count();
        $activePointsCount = $allTemplates->filter(fn($t) => (bool)(is_object($t) ? $t->status : ($t['status'] ?? true)))->count();
        $authoritiesCount = $availableCategories->count();

        // Paginate filtered templates (10 items per page by default)
        $currentPage = (int) ($inputs['page'] ?? request()->get('page', 1));
        $perPage = 10;
        $totalResults = $filteredTemplates->count();
        $paginatedItems = $filteredTemplates->forPage($currentPage, $perPage)->values();

        $paginatedTemplates = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedItems,
            $totalResults,
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        return [
            'companies'            => $companies,
            'selectedCompanyId'    => $selectedCompanyId,
            'selectedCategory'     => $selectedCategory,
            'selectedStatus'       => $selectedStatus,
            'selectedMandatory'    => $selectedMandatory,
            'search'               => $search,
            'sortBy'               => $sortBy,
            'sortOrder'            => $sortOrder,
            'hasActiveFilters'     => $hasActiveFilters,
            'clearanceCategories'  => $clearanceCategories,
            'clearanceTemplates'   => $paginatedTemplates,
            'allTemplatesCount'    => $totalPointsCount,
            'totalPointsCount'     => $totalPointsCount,
            'mandatoryPointsCount' => $mandatoryPointsCount,
            'activePointsCount'    => $activePointsCount,
            'authoritiesCount'     => $authoritiesCount,
            'availableCategories'  => $availableCategories,
        ];
    }

    public function storeTemplate(array $validated, Request $request): int
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $rawCat = $validated['clearance_category'] ?? ($validated['clearance_category_select'] ?? 'hr_operations');
        $categoryKey = Str::slug($rawCat, '_');
        $meta = ExitClearanceTemplate::getCategoryMetadata($categoryKey);
        $categoryName = !empty($validated['category_name']) ? trim($validated['category_name']) : $meta['name'];
        $companyId = !empty($validated['company_id']) ? (int)$validated['company_id'] : null;

        if ($tenantId) {
            $tenantHasTemplates = ExitClearanceTemplate::where('tenant_id', $tenantId)->exists();
            if (!$tenantHasTemplates) {
                $this->clearanceService->resetTemplatesToDefaults($tenantId, $companyId);
            }
        }

        return DB::transaction(function () use ($validated, $tenantId, $companyId, $categoryKey, $categoryName, $request) {
            $createdCount = 0;

            if (!empty($validated['items']) && is_array($validated['items'])) {
                foreach ($validated['items'] as $itemData) {
                    $itemName = trim($itemData['item_name'] ?? '');
                    if ($itemName === '') {
                        continue;
                    }

                    $isMandatory = isset($itemData['is_mandatory']) && ($itemData['is_mandatory'] == '1' || $itemData['is_mandatory'] === true || $itemData['is_mandatory'] === 'on');
                    $sortOrder = isset($itemData['sort_order']) && $itemData['sort_order'] !== '' 
                        ? (int) $itemData['sort_order'] 
                        : (((int) ExitClearanceTemplate::where('tenant_id', $tenantId)->max('sort_order')) + 1);
                    $status = isset($itemData['status']) ? ($itemData['status'] == '1' || $itemData['status'] === true || $itemData['status'] === 'on' || $itemData['status'] === 'active') : true;

                    ExitClearanceTemplate::create([
                        'tenant_id'          => $tenantId,
                        'company_id'         => $companyId,
                        'clearance_category' => $categoryKey,
                        'category_name'      => $categoryName,
                        'item_name'          => $itemName,
                        'description'        => !empty($itemData['description']) ? trim($itemData['description']) : null,
                        'is_mandatory'       => $isMandatory,
                        'sort_order'         => $sortOrder,
                        'status'             => $status,
                    ]);

                    $createdCount++;
                }
            } elseif (!empty($validated['item_name'])) {
                $isMandatory = isset($validated['is_mandatory']) && ($validated['is_mandatory'] == '1' || $validated['is_mandatory'] === true || $validated['is_mandatory'] === 'on');
                $sortOrder = isset($validated['sort_order']) && $validated['sort_order'] !== '' 
                    ? (int) $validated['sort_order'] 
                    : (((int) ExitClearanceTemplate::where('tenant_id', $tenantId)->max('sort_order')) + 1);
                $status = isset($validated['status']) ? ($validated['status'] == '1' || $validated['status'] === true || $validated['status'] === 'on' || $validated['status'] === 'active') : true;

                ExitClearanceTemplate::create([
                    'tenant_id'          => $tenantId,
                    'company_id'         => $companyId,
                    'clearance_category' => $categoryKey,
                    'category_name'      => $categoryName,
                    'item_name'          => trim($validated['item_name']),
                    'description'        => !empty($validated['description']) ? trim($validated['description']) : null,
                    'is_mandatory'       => $isMandatory,
                    'sort_order'         => $sortOrder,
                    'status'             => $status,
                ]);

                $createdCount++;
            }

            return $createdCount;
        });
    }

    public function updateTemplate(ExitClearanceTemplate $template, array $validated, Request $request): bool
    {
        $rawCat = $validated['clearance_category'] ?? ($validated['clearance_category_select'] ?? $template->clearance_category);
        $categoryKey = Str::slug($rawCat, '_');
        $meta = ExitClearanceTemplate::getCategoryMetadata($categoryKey);
        $categoryName = !empty($validated['category_name']) ? trim($validated['category_name']) : $meta['name'];
        $companyId = !empty($validated['company_id']) ? (int)$validated['company_id'] : null;

        return DB::transaction(function () use ($template, $validated, $companyId, $categoryKey, $categoryName, $request) {
            $isMandatory = isset($validated['is_mandatory']) && ($validated['is_mandatory'] == '1' || $validated['is_mandatory'] === true || $validated['is_mandatory'] === 'on');
            $status = isset($validated['status']) 
                ? ($validated['status'] == '1' || $validated['status'] === true || $validated['status'] === 'on' || $validated['status'] === 'active') 
                : ($template->status ?? true);
            $sortOrder = isset($validated['sort_order']) && $validated['sort_order'] !== '' 
                ? (int)$validated['sort_order'] 
                : ($template->sort_order ?? 0);

            return $template->update([
                'company_id'         => $companyId,
                'clearance_category' => $categoryKey,
                'category_name'      => $categoryName,
                'item_name'          => trim($validated['item_name']),
                'description'        => $validated['description'] ?? null,
                'is_mandatory'       => $isMandatory,
                'sort_order'         => $sortOrder,
                'status'             => $status,
            ]);
        });
    }

    public function deleteTemplate(ExitClearanceTemplate $template): bool
    {
        return (bool) $template->delete();
    }

    public function deleteCategory(string $categoryKey, ?int $companyId): int
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $query = ExitClearanceTemplate::where('tenant_id', $tenantId)
            ->where('clearance_category', $categoryKey);

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        $count = $query->count();
        $query->delete();

        return $count;
    }

    public function resetTemplatesToDefaults(?int $companyId): void
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $this->clearanceService->resetTemplatesToDefaults($tenantId, $companyId);
    }
}
