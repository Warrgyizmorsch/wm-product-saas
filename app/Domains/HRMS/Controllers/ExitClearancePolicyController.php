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
        $this->authorize('viewAny', ExitClearanceTemplate::class);

        $data = $this->policyRepository->getIndexData($request->all());

        return view('modules.hrms.offboarding-policies.index', $data);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', ExitClearanceTemplate::class);
        if (empty($request->input('clearance_category')) && $request->filled('clearance_category_select')) {
            $request->merge(['clearance_category' => $request->input('clearance_category_select')]);
        }

        $validated = $request->validate([
            'company_id'                => 'nullable|exists:companies,id',
            'clearance_category'        => 'nullable|string|max:100',
            'clearance_category_select' => 'nullable|string|max:100',
            'category_name'             => 'nullable|string|max:150',
            'items'                     => 'nullable|array|min:1',
            'items.*.item_name'         => 'nullable|string|max:255',
            'items.*.description'       => 'nullable|string|max:1000',
            'items.*.sort_order'        => 'nullable|integer|min:0',
            'items.*.is_mandatory'      => 'nullable',
            'items.*.status'            => 'nullable',
            // Fallback for single direct item
            'item_name'                 => 'nullable|string|max:255',
            'description'               => 'nullable|string|max:1000',
            'is_mandatory'              => 'nullable',
            'sort_order'                => 'nullable|integer|min:0',
            'status'                    => 'nullable',
        ]);

        $companyId = !empty($validated['company_id']) ? (int) $validated['company_id'] : null;
        $createdCount = $this->policyRepository->storeTemplate($validated, $request);

        if ($createdCount === 0) {
            return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
                ->with('error', __('hrms.offboarding_policies.msg_provide_valid_point'));
        }

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
            ->with('success', __('hrms.offboarding_policies.msg_points_saved', ['count' => $createdCount]));
    }

    public function update(Request $request, ExitClearanceTemplate $template): RedirectResponse
    {
        $this->authorize('update', $template);
        if (empty($request->input('clearance_category')) && $request->filled('clearance_category_select')) {
            $request->merge(['clearance_category' => $request->input('clearance_category_select')]);
        }

        $validated = $request->validate([
            'company_id'                => 'nullable|exists:companies,id',
            'clearance_category'        => 'nullable|string|max:100',
            'clearance_category_select' => 'nullable|string|max:100',
            'category_name'             => 'nullable|string|max:150',
            'item_name'                 => 'required|string|max:255',
            'description'               => 'nullable|string|max:1000',
            'is_mandatory'              => 'nullable',
            'sort_order'                => 'nullable|integer|min:0',
            'status'                    => 'nullable',
        ]);

        $this->policyRepository->updateTemplate($template, $validated, $request);

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $template->company_id])
            ->with('success', __('hrms.offboarding_policies.msg_point_updated', ['name' => $template->item_name]));
    }

    public function destroy(ExitClearanceTemplate $template): RedirectResponse
    {
        $this->authorize('delete', $template);
        $name = $template->item_name;
        $companyId = $template->company_id;
        $this->policyRepository->deleteTemplate($template);

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
            ->with('success', __('hrms.offboarding_policies.msg_point_deleted', ['name' => $name]));
    }

    public function destroyCategory(Request $request): RedirectResponse
    {
        $this->authorize('delete', ExitClearanceTemplate::class);
        $categoryKey = (string) $request->input('clearance_category');
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        $count = $this->policyRepository->deleteCategory($categoryKey, $companyId);

        $meta = ExitClearanceTemplate::getCategoryMetadata($categoryKey);
        $categoryName = $meta['name'] ?? ucwords(str_replace(['_', '-'], ' ', $categoryKey));

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
            ->with('success', __('hrms.offboarding_policies.msg_category_deleted', ['name' => $categoryName, 'count' => $count]));
    }

    public function reset(Request $request): RedirectResponse
    {
        $this->authorize('create', ExitClearanceTemplate::class);
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        $this->policyRepository->resetTemplatesToDefaults($companyId);

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])
            ->with('success', __('hrms.offboarding_policies.msg_reset_success'));
    }
}
