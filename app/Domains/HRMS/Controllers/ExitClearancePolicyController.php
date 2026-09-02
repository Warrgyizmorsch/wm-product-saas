<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\ExitClearanceTemplate;
use App\Domains\HRMS\Services\ExitClearanceService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ExitClearancePolicyController extends Controller
{
    public function __construct(
        private readonly ExitClearanceService $clearanceService
    ) {}

    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $selectedCompanyId = $request->input('company_id');
        $selectedCategory = $request->input('category');
        $selectedStatus = $request->input('status');
        $selectedMandatory = $request->input('is_mandatory');
        $search = $request->input('search');

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
                'category_key' => $categoryKey,
                'category_name' => $meta['name'] ?? $meta['title'] ?? $categoryName ?? ucwords(str_replace(['_', '-'], ' ', $categoryKey)),
                'color' => $meta['color'] ?? 'primary',
                'icon' => $meta['icon'] ?? 'feather-check-circle',
                'items' => $items,
                'total_items' => $items->count(),
                'active_items' => $items->filter(fn($i) => (bool) (is_object($i) ? $i->status : ($i['status'] ?? true)))->count(),
            ];
        });

        $availableCategories = $allTemplates->mapWithKeys(function ($item) {
            $key = is_object($item) ? $item->clearance_category : ($item['clearance_category'] ?? '');
            $name = is_object($item) ? ($item->category_name ?? $key) : ($item['category_name'] ?? $key);
            $meta = ExitClearanceTemplate::getCategoryMetadata($key, $name);
            return [$key => $meta['name'] ?? $meta['title'] ?? $name];
        })->filter();

        return view('modules.hrms.offboarding-policies.index', [
            'companies' => $companies,
            'selectedCompanyId' => $selectedCompanyId,
            'selectedCategory' => $selectedCategory,
            'selectedStatus' => $selectedStatus,
            'selectedMandatory' => $selectedMandatory,
            'search' => $search,
            'clearanceCategories' => $clearanceCategories,
            'clearanceTemplates' => $filteredTemplates,
            'allTemplatesCount' => $allTemplates->count(),
            'availableCategories' => $availableCategories,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (empty($request->input('clearance_category')) && $request->filled('clearance_category_select')) {
            $request->merge(['clearance_category' => $request->input('clearance_category_select')]);
        }

        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'clearance_category' => 'required|string|max:100',
            'category_name' => 'required|string|max:150',
            'items' => 'nullable|array|min:1',
            'items.*.item_name' => 'nullable|string|max:255',
            'items.*.description' => 'nullable|string|max:1000',
            'items.*.sort_order' => 'nullable|integer|min:0',
            'items.*.is_mandatory' => 'nullable',
            // Fallback for single item
            'item_name' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_mandatory' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

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

        $createdCount = 0;

        if (!empty($validated['items']) && is_array($validated['items'])) {
            foreach ($validated['items'] as $itemData) {
                $itemName = trim($itemData['item_name'] ?? '');
                if ($itemName === '') {
                    continue;
                }

                $isMandatory = isset($itemData['is_mandatory']) && ($itemData['is_mandatory'] == '1' || $itemData['is_mandatory'] === true || $itemData['is_mandatory'] === 'on');

                ExitClearanceTemplate::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $companyId,
                    'clearance_category' => $categoryKey,
                    'category_name' => $categoryName,
                    'item_name' => $itemName,
                    'description' => !empty($itemData['description']) ? trim($itemData['description']) : null,
                    'is_mandatory' => $isMandatory,
                    'sort_order' => isset($itemData['sort_order']) && $itemData['sort_order'] !== '' ? (int) $itemData['sort_order'] : 0,
                    'status' => true,
                ]);

                $createdCount++;
            }
        } elseif (!empty($validated['item_name'])) {
            ExitClearanceTemplate::create([
                'tenant_id' => $tenantId,
                'company_id' => $companyId,
                'clearance_category' => $categoryKey,
                'category_name' => $categoryName,
                'item_name' => trim($validated['item_name']),
                'description' => $validated['description'] ?? null,
                'is_mandatory' => $request->boolean('is_mandatory', true),
                'sort_order' => $validated['sort_order'] ?? 0,
                'status' => true,
            ]);

            $createdCount++;
        }

        if ($createdCount === 0) {
            return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
                ->with('error', 'Please provide at least one valid checklist item name.');
        }

        $plural = $createdCount === 1 ? 'checklist point' : 'checklist points';
        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
            ->with('success', "{$createdCount} clearance {$plural} added under '{$categoryName}'.");
    }

    public function update(Request $request, ExitClearanceTemplate $template): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'clearance_category' => 'required|string|max:100',
            'category_name' => 'required|string|max:150',
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_mandatory' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'status' => 'nullable|boolean',
        ]);

        $categoryKey = Str::slug($validated['clearance_category'], '_');

        $template->update([
            'company_id' => $validated['company_id'] ?: null,
            'clearance_category' => $categoryKey,
            'category_name' => trim($validated['category_name']),
            'item_name' => trim($validated['item_name']),
            'description' => $validated['description'] ?? null,
            'is_mandatory' => $request->boolean('is_mandatory'),
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => $request->boolean('status', true),
        ]);

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $template->company_id])
            ->with('success', "Clearance checklist point '{$template->item_name}' updated successfully.");
    }

    public function destroy(ExitClearanceTemplate $template): RedirectResponse
    {
        $name = $template->item_name;
        $companyId = $template->company_id;
        $template->delete();

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
            ->with('success', "Clearance point '{$name}' removed from policy.");
    }

    public function destroyCategory(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $categoryKey = $request->input('clearance_category');
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        $query = ExitClearanceTemplate::where('tenant_id', $tenantId)
            ->where('clearance_category', $categoryKey);

        if ($companyId) {
            $query->where('company_id', $companyId);
        } else {
            $query->whereNull('company_id');
        }

        $count = $query->count();
        $query->delete();

        $meta = ExitClearanceTemplate::getCategoryMetadata($categoryKey);
        $categoryName = $meta['name'] ?? ucwords(str_replace(['_', '-'], ' ', $categoryKey));

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
            ->with('success', "Category '{$categoryName}' and its {$count} checklist point(s) were completely deleted.");
    }

    public function reset(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        $this->clearanceService->resetTemplatesToDefaults($tenantId, $companyId);

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
            ->with('success', "Clearance policies reset to standard 12 default checklist points.");
    }
}
