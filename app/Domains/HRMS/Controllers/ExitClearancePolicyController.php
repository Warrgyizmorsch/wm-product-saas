<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\ExitClearanceTemplate;
use App\Domains\HRMS\Repositories\ExitClearancePolicyRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExitClearancePolicyController extends Controller
{
    public function __construct(
        private readonly ExitClearancePolicyRepositoryInterface $policyRepository
    ) {
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', \App\Domains\HRMS\Models\ExitClearanceItem::class);

        $data = $this->policyRepository->getIndexData($request->all());

        return view('modules.hrms.offboarding-policies.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', \App\Domains\HRMS\Models\ExitClearanceItem::class);
        if (empty($request->input('clearance_category')) && $request->filled('clearance_category_select')) {
            $request->merge(['clearance_category' => $request->input('clearance_category_select')]);
        }

        $validated = $request->validate([
            'company_id'         => 'nullable|exists:companies,id',
            'clearance_category' => 'required|string|max:100',
            'category_name'      => 'required|string|max:150',
            'items'              => 'nullable|array|min:1',
            'items.*.item_name'  => 'nullable|string|max:255',
            'items.*.description'=> 'nullable|string|max:1000',
            'items.*.sort_order' => 'nullable|integer|min:0',
            'items.*.is_mandatory'=> 'nullable',
            // Fallback for single item
            'item_name'          => 'nullable|string|max:255',
            'description'        => 'nullable|string|max:1000',
            'is_mandatory'       => 'nullable|boolean',
            'sort_order'         => 'nullable|integer|min:0',
        ]);

        $companyId = $validated['company_id'] ?: null;
        $categoryName = trim($validated['category_name']);

        $createdCount = $this->policyRepository->storeTemplate($validated, $request);

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
        $this->authorize('update', \App\Domains\HRMS\Models\ExitClearanceItem::class);
        $validated = $request->validate([
            'company_id'         => 'nullable|exists:companies,id',
            'clearance_category' => 'required|string|max:100',
            'category_name'      => 'required|string|max:150',
            'item_name'          => 'required|string|max:255',
            'description'        => 'nullable|string|max:1000',
            'is_mandatory'       => 'nullable|boolean',
            'sort_order'         => 'nullable|integer|min:0',
            'status'             => 'nullable|boolean',
        ]);

        $this->policyRepository->updateTemplate($template, $validated, $request);

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $template->company_id])
            ->with('success', "Clearance checklist point '{$template->item_name}' updated successfully.");
    }

    public function destroy(ExitClearanceTemplate $template): RedirectResponse
    {
        $this->authorize('delete', \App\Domains\HRMS\Models\ExitClearanceItem::class);
        $name = $template->item_name;
        $companyId = $template->company_id;
        $this->policyRepository->deleteTemplate($template);

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
            ->with('success', "Clearance point '{$name}' removed from policy.");
    }

    public function destroyCategory(Request $request): RedirectResponse
    {
        $categoryKey = (string) $request->input('clearance_category');
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        $count = $this->policyRepository->deleteCategory($categoryKey, $companyId);

        $meta = ExitClearanceTemplate::getCategoryMetadata($categoryKey);
        $categoryName = $meta['name'] ?? ucwords(str_replace(['_', '-'], ' ', $categoryKey));

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
            ->with('success', "Category '{$categoryName}' and its {$count} checklist point(s) were completely deleted.");
    }

    public function reset(Request $request): RedirectResponse
    {
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        $this->policyRepository->resetTemplatesToDefaults($companyId);

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
            ->with('success', "Clearance policies reset to standard 12 default checklist points.");
    }
}
