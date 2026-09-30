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
                $expectedStatus = (bool) $selectedStatus;
                if ($status !== $expectedStatus) {
                    return false;
                }
            }

            if ($selectedMandatory !== null && $selectedMandatory !== '') {
                $expectedMandatory = (bool) $selectedMandatory;
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

        $availableCategories = $allTemplates->mapWithKeys(function ($item) {
            $key = is_object($item) ? $item->clearance_category : ($item['clearance_category'] ?? '');
            $name = is_object($item) ? ($item->category_name ?? $key) : ($item['category_name'] ?? $key);
            $meta = ExitClearanceTemplate::getCategoryMetadata($key, $name);
            return [$key => $meta['name'] ?? $meta['title'] ?? $name];
        })->filter();

        return [
            'companies'           => $companies,
            'selectedCompanyId'   => $selectedCompanyId,
            'selectedCategory'    => $selectedCategory,
            'selectedStatus'      => $selectedStatus,
            'selectedMandatory'   => $selectedMandatory,
            'search'              => $search,
            'clearanceCategories' => $clearanceCategories,
            'clearanceTemplates'  => $filteredTemplates,
            'allTemplatesCount'   => $allTemplates->count(),
            'availableCategories' => $availableCategories,
        ];
    }

    public function storeTemplate(array $validated, Request $request): int
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $categoryKey = Str::slug($validated['clearance_category'], '_');
        $categoryName = trim($validated['category_name']);
        $companyId = $validated['company_id'] ?: null;

        if ($tenantId) {
            $tenantHasTemplates = ExitClearanceTemplate::where('tenant_id', $tenantId)->exists();
            if (!$tenantHasTemplates) {
                $this->clearanceService->resetTemplatesToDefaults($tenantId, null);
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

                    ExitClearanceTemplate::create([
                        'tenant_id'          => $tenantId,
                        'company_id'         => $companyId,
                        'clearance_category' => $categoryKey,
                        'category_name'      => $categoryName,
                        'item_name'          => $itemName,
                        'description'        => !empty($itemData['description']) ? trim($itemData['description']) : null,
                        'is_mandatory'       => $isMandatory,
                        'sort_order'         => isset($itemData['sort_order']) && $itemData['sort_order'] !== '' ? (int) $itemData['sort_order'] : 0,
                        'status'             => true,
                    ]);

                    $createdCount++;
                }
            } elseif (!empty($validated['item_name'])) {
                ExitClearanceTemplate::create([
                    'tenant_id'          => $tenantId,
                    'company_id'         => $companyId,
                    'clearance_category' => $categoryKey,
                    'category_name'      => $categoryName,
                    'item_name'          => trim($validated['item_name']),
                    'description'        => $validated['description'] ?? null,
                    'is_mandatory'       => $request->boolean('is_mandatory', true),
                    'sort_order'         => $validated['sort_order'] ?? 0,
                    'status'             => true,
                ]);

                $createdCount++;
            }

            return $createdCount;
        });
    }

    public function updateTemplate(ExitClearanceTemplate $template, array $validated, Request $request): bool
    {
        $categoryKey = Str::slug($validated['clearance_category'], '_');

        return DB::transaction(function () use ($template, $validated, $categoryKey, $request) {
            return $template->update([
                'company_id'         => $validated['company_id'] ?: null,
                'clearance_category' => $categoryKey,
                'category_name'      => trim($validated['category_name']),
                'item_name'          => trim($validated['item_name']),
                'description'        => $validated['description'] ?? null,
                'is_mandatory'       => $request->boolean('is_mandatory'),
                'sort_order'         => $validated['sort_order'] ?? 0,
                'status'             => $request->boolean('status', true),
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
