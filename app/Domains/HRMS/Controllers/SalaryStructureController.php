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
        $this->authorize('viewAny', SalaryStructure::class);

        $data = $this->salaryStructureRepository->getIndexData($request->all());

        return view('modules.hrms.salary-structure.index', $data);
    }

    public function storeStructure(Request $request)
    {
        $this->authorize('create', SalaryStructure::class);

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
            $redirectUrl .= '?pay_group_id=' . $validated['pay_group_id'] . '&tab=structures';
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.structure_created_success'));
    }

    public function updateStructure(Request $request, SalaryStructure $salaryStructure)
    {
        $this->authorize('update', $salaryStructure);

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
            $redirectUrl .= '?pay_group_id=' . $salaryStructure->pay_group_id . '&tab=structures';
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.structure_updated_success'));
    }

    public function destroyStructure(SalaryStructure $salaryStructure)
    {
        $this->authorize('delete', $salaryStructure);

        $payGroupId = $salaryStructure->pay_group_id;
        $this->salaryStructureRepository->destroyStructure($salaryStructure);

        $redirectUrl = route('hrms.salary-structure.index');
        if ($payGroupId) {
            $redirectUrl .= '?pay_group_id=' . $payGroupId . '&tab=structures';
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.structure_deleted_success'));
    }

    public function storeComponent(Request $request)
    {
        $this->authorize('create', SalaryStructure::class);

        $rules = [
            'pay_group_id' => 'nullable|exists:pay_groups,id',
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'type' => 'required|in:earning,deduction',
            'calculation_type' => 'nullable|string|max:50',
            'chart_of_account_id' => 'nullable|exists:chart_of_accounts,id',
            'is_adhoc' => 'required|boolean',
            'status' => 'required|boolean',
            'description' => 'nullable|string',
        ];

        $validated = $request->validate($rules);

        if (empty($validated['calculation_type'])) {
            $validated['calculation_type'] = 'fixed';
        }

        if (!empty($validated['pay_group_id'])) {
            $payGroup = PayGroup::find($validated['pay_group_id']);
            if ($payGroup) {
                $validated['company_id'] = $payGroup->company_id;
            }
        }

        $component = $this->salaryStructureRepository->storeComponent($validated);

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => __('hrms.salary.component_created_success'),
                'component' => [
                    'id' => $component->id,
                    'name' => $component->name,
                    'code' => $component->code,
                    'type' => $component->type,
                    'is_adhoc' => $component->is_adhoc,
                ],
            ]);
        }

        $redirectUrl = route('hrms.salary-structure.index');
        if (!empty($validated['pay_group_id'])) {
            $subtab = $validated['is_adhoc'] ? 'adhoc' : 'recurring';
            $redirectUrl .= '?pay_group_id=' . $validated['pay_group_id'] . '&tab=components&subtab=' . $subtab;
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.component_created_success'));
    }

    public function updateComponent(Request $request, SalaryComponent $salaryComponent)
    {
        $this->authorize('update', SalaryStructure::class);

        $rules = [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50',
            'type' => 'required|in:earning,deduction',
            'calculation_type' => 'nullable|string|max:50',
            'chart_of_account_id' => 'nullable|exists:chart_of_accounts,id',
            'is_adhoc' => 'required|boolean',
            'status' => 'required|boolean',
            'description' => 'nullable|string',
        ];

        $validated = $request->validate($rules);

        if (empty($validated['calculation_type'])) {
            $validated['calculation_type'] = 'fixed';
        }

        $this->salaryStructureRepository->updateComponent($salaryComponent, $validated);

        $redirectUrl = route('hrms.salary-structure.index');
        if ($salaryComponent->pay_group_id) {
            $subtab = $salaryComponent->is_adhoc ? 'adhoc' : 'recurring';
            $redirectUrl .= '?pay_group_id=' . $salaryComponent->pay_group_id . '&tab=components&subtab=' . $subtab;
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.component_updated_success'));
    }

    public function destroyComponent(SalaryComponent $salaryComponent)
    {
        $this->authorize('delete', SalaryStructure::class);

        $payGroupId = $salaryComponent->pay_group_id;
        $isAdhoc = $salaryComponent->is_adhoc;

        $this->salaryStructureRepository->destroyComponent($salaryComponent);

        $redirectUrl = route('hrms.salary-structure.index');
        if ($payGroupId) {
            $subtab = $isAdhoc ? 'adhoc' : 'recurring';
            $redirectUrl .= '?pay_group_id=' . $payGroupId . '&tab=components&subtab=' . $subtab;
        }

        return redirect($redirectUrl)->with('success', __('hrms.salary.component_deleted_success'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Pay Groups
    // ─────────────────────────────────────────────────────────────────────────

    public function storePayGroup(Request $request)
    {
        $this->authorize('create', SalaryStructure::class);

        $validated = $request->validate([
            'name'        => 'required|max:255',
            'company_id'  => 'nullable|integer|exists:companies,id',
            'description' => 'nullable',
            'status'      => 'required',
        ]);

        $validated['status'] = in_array($request->status, ['success', '1', 'active', true], true);

        $newPayGroup = $this->salaryStructureRepository->storePayGroup($validated);

        return redirect()->route('hrms.salary-structure.index', ['pay_group_id' => $newPayGroup->id])->with('success', __('hrms.salary.pay_group_created_success'));
    }

    public function updatePayGroup(Request $request, PayGroup $payGroup)
    {
        $this->authorize('update', SalaryStructure::class);

        $validated = $request->validate([
            'name'        => 'required|max:255',
            'company_id'  => 'nullable|integer|exists:companies,id',
            'description' => 'nullable',
            'status'      => 'required',
        ]);

        $validated['status'] = in_array($request->status, ['success', '1', 'active', true], true);

        $this->salaryStructureRepository->updatePayGroup($payGroup, $validated);

        return redirect()->route('hrms.salary-structure.index', ['pay_group_id' => $payGroup->id])->with('success', __('hrms.salary.pay_group_updated_success'));
    }

    public function updatePayGroupRules(Request $request, PayGroup $payGroup)
    {
        $this->authorize('update', SalaryStructure::class);

        $rules = $payGroup->payroll_rules ?? [];

        // Checkboxes return nothing if unchecked, so we merge explicit booleans and default fallbacks
        $request->merge([
            'enable_pf'              => $request->has('enable_pf') ? $request->boolean('enable_pf') : ($rules['enable_pf'] ?? true),
            'restrict_pf_ceiling'    => $request->has('restrict_pf_ceiling'),
            'pf_wage_ceiling'        => $request->filled('pf_wage_ceiling') ? (float)$request->input('pf_wage_ceiling') : ($rules['pf_wage_ceiling'] ?? 15000.00),
            'enable_esi'             => $request->has('enable_esi') ? $request->boolean('enable_esi') : ($rules['enable_esi'] ?? true),
            'restrict_esi_threshold' => $request->has('restrict_esi_threshold'),
            'esi_gross_threshold'    => $request->filled('esi_gross_threshold') ? (float)$request->input('esi_gross_threshold') : ($rules['esi_gross_threshold'] ?? 21000.00),
        ]);

        $validated = $request->validate([
            'proration_rule'         => 'required|in:calendar_days,fixed_30_days,working_days',
            'lop_splicing_rule'      => 'required|in:proportionate_gross,basic_hra_only',
            'attendance_lock_day'    => 'required|integer|min:1|max:31',
            'variable_lock_day'      => 'required|integer|min:1|max:31',
            'enable_pf'              => 'nullable|boolean',
            'restrict_pf_ceiling'    => 'required|boolean',
            'pf_wage_ceiling'        => 'nullable|numeric|min:0',
            'enable_esi'             => 'nullable|boolean',
            'restrict_esi_threshold' => 'required|boolean',
            'esi_gross_threshold'    => 'nullable|numeric|min:0',
        ]);

        $this->salaryStructureRepository->updatePayGroupRules($payGroup, $validated);

        return redirect()->route('hrms.salary-structure.index', [
            'pay_group_id' => $payGroup->id,
            'tab'          => 'rules'
        ])->with('success', __('hrms.salary.rules_updated_success'));
    }

    public function destroyPayGroup(PayGroup $payGroup)
    {
        $this->authorize('delete', SalaryStructure::class);

        $this->salaryStructureRepository->destroyPayGroup($payGroup);

        return redirect()->route('hrms.salary-structure.index')->with('success', __('hrms.salary.pay_group_deleted_success'));
    }
}

