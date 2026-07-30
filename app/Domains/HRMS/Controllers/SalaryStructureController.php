<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\PayGroup;
use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Models\SalaryStructure;
use App\Domains\HRMS\Repositories\SalaryStructureRepositoryInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SalaryStructureController extends Controller
{
    public function __construct(
        private readonly SalaryStructureRepositoryInterface $salaryStructureRepository
    ) {}

    public function index(Request $request)
    {
        $data = $this->salaryStructureRepository->getIndexData($request->all());

        return view('modules.hrms.salary-structure.index', $data);
    }

    public function storeStructure(Request $request)
    {
        $rules = [
            'pay_group_id' => 'nullable|exists:pay_groups,id',
            'name' => 'required|string|max:255',
            'min_ctc' => 'required|numeric|min:0',
            'max_ctc' => 'required|numeric|min:0',
            'status' => 'required|boolean',
            'components' => 'nullable|array',
        ];

        $validated = $request->validate($rules);

        if (!empty($validated['pay_group_id'])) {
            $payGroup = PayGroup::find($validated['pay_group_id']);
            if ($payGroup) {
                $validated['company_id'] = $payGroup->company_id;
            }
        }

        $this->salaryStructureRepository->storeStructure($validated);

        $redirectUrl = route('hrms.salary-structure.index');
        if (!empty($validated['pay_group_id'])) {
            $redirectUrl .= '?pay_group_id=' . $validated['pay_group_id'] . '&active_tab=structures';
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.structure_created_success'));
    }

    public function updateStructure(Request $request, SalaryStructure $salaryStructure)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'min_ctc' => 'required|numeric|min:0',
            'max_ctc' => 'required|numeric|min:0',
            'status' => 'required|boolean',
            'components' => 'nullable|array',
        ];

        $validated = $request->validate($rules);
        $this->salaryStructureRepository->updateStructure($salaryStructure, $validated);

        $redirectUrl = route('hrms.salary-structure.index');
        if ($salaryStructure->pay_group_id) {
            $redirectUrl .= '?pay_group_id=' . $salaryStructure->pay_group_id . '&active_tab=structures';
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.structure_updated_success'));
    }

    public function destroyStructure(SalaryStructure $salaryStructure)
    {
        $payGroupId = $salaryStructure->pay_group_id;
        $this->salaryStructureRepository->destroyStructure($salaryStructure);

        $redirectUrl = route('hrms.salary-structure.index');
        if ($payGroupId) {
            $redirectUrl .= '?pay_group_id=' . $payGroupId . '&active_tab=structures';
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.structure_deleted_success'));
    }

    public function storeComponent(Request $request)
    {
        $rules = [
            'pay_group_id' => 'nullable|exists:pay_groups,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'type' => 'required|in:earning,deduction',
            'is_adhoc' => 'required|boolean',
            'status' => 'required|boolean',
            'description' => 'nullable|string',
        ];

        $validated = $request->validate($rules);

        if (!empty($validated['pay_group_id'])) {
            $payGroup = PayGroup::find($validated['pay_group_id']);
            if ($payGroup) {
                $validated['company_id'] = $payGroup->company_id;
            }
        }

        $this->salaryStructureRepository->storeComponent($validated);

        $redirectUrl = route('hrms.salary-structure.index');
        if (!empty($validated['pay_group_id'])) {
            $tab = $validated['is_adhoc'] ? 'components-adhoc' : 'components-recurring';
            $redirectUrl .= '?pay_group_id=' . $validated['pay_group_id'] . '&active_tab=' . $tab;
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.component_created_success'));
    }

    public function updateComponent(Request $request, SalaryComponent $salaryComponent)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'type' => 'required|in:earning,deduction',
            'is_adhoc' => 'required|boolean',
            'status' => 'required|boolean',
            'description' => 'nullable|string',
        ];

        $validated = $request->validate($rules);
        $this->salaryStructureRepository->updateComponent($salaryComponent, $validated);

        $redirectUrl = route('hrms.salary-structure.index');
        if ($salaryComponent->pay_group_id) {
            $tab = $salaryComponent->is_adhoc ? 'components-adhoc' : 'components-recurring';
            $redirectUrl .= '?pay_group_id=' . $salaryComponent->pay_group_id . '&active_tab=' . $tab;
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.component_updated_success'));
    }

    public function destroyComponent(SalaryComponent $salaryComponent)
    {
        $payGroupId = $salaryComponent->pay_group_id;
        $isAdhoc = $salaryComponent->is_adhoc;

        $this->salaryStructureRepository->destroyComponent($salaryComponent);

        $redirectUrl = route('hrms.salary-structure.index');
        if ($payGroupId) {
            $tab = $isAdhoc ? 'components-adhoc' : 'components-recurring';
            $redirectUrl .= '?pay_group_id=' . $payGroupId . '&active_tab=' . $tab;
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.component_deleted_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pay Groups
    // ─────────────────────────────────────────────────────────────────────────

    public function storePayGroup(Request $request)
    {
        $validated = $request->validate([
            'name'        => 'required|max:255',
            'company_id'  => 'nullable|integer|exists:companies,id',
            'description' => 'nullable',
            'status'      => 'required',
        ]);

        $status = in_array($request->status, ['success', '1', 'active', true], true);

        PayGroup::create([
            'company_id'  => $validated['company_id'] ?? null,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status'      => $status,
        ]);

        return redirect()->route('hrms.salary-structure.index')->with('success', 'Pay group created successfully.');
    }

    public function updatePayGroup(Request $request, PayGroup $payGroup)
    {
        $validated = $request->validate([
            'name'        => 'required|max:255',
            'company_id'  => 'nullable|integer|exists:companies,id',
            'description' => 'nullable',
            'status'      => 'required',
        ]);

        $status = in_array($request->status, ['success', '1', 'active', true], true);

        $payGroup->update([
            'company_id'  => $validated['company_id'] ?? $payGroup->company_id,
            'name'        => $validated['name'],
            'description' => $validated['description'] ?? null,
            'status'      => $status,
        ]);

        return redirect()->route('hrms.salary-structure.index')->with('success', 'Pay group updated successfully.');
    }

    public function destroyPayGroup(PayGroup $payGroup)
    {
        $payGroup->delete();

        return redirect()->route('hrms.salary-structure.index')->with('success', 'Pay group deleted successfully.');
    }
}

