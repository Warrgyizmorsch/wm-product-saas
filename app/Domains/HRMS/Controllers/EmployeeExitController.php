<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetAllocation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Models\EmployeeExitDocument;
use App\Domains\HRMS\Models\EmployeeFnfSettlement;
use App\Domains\HRMS\Repositories\EmployeeExitRepositoryInterface;
use App\Domains\HRMS\Services\ExitClearanceService;
use App\Domains\HRMS\Services\ExitDocumentationService;
use App\Domains\HRMS\Services\FnFCalculationService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class EmployeeExitController extends Controller
{
    public function __construct(
        private readonly EmployeeExitRepositoryInterface $exitRepository,
        private readonly ExitDocumentationService $docService,
        private readonly ExitClearanceService $clearanceService,
        private readonly FnFCalculationService $fnfService
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', EmployeeExit::class);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();

        $data = $this->exitRepository->getIndexData($request->all(), $user, $tenantId);

        return view('modules.hrms.employees.exits.index', $data);
    }

    public function show(int $id): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $data = $this->exitRepository->getShowData($id, $tenantId);

        $this->authorize('view', $data['exit']);

        return view('modules.hrms.employees.exits.index', array_merge($data, ['selectedExitId' => $id]));
    }

    public function initiate(Request $request): RedirectResponse
    {
        $this->authorize('create', EmployeeExit::class);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'employee_id'        => 'required|exists:employees,id',
            'separation_type'    => 'required|string|in:resignation,termination,retirement,absconding,death',
            'resignation_date'   => 'required|date',
            'preferred_lwd'      => 'nullable|date|after_or_equal:resignation_date',
            'notice_period_days' => 'nullable|integer|min:0',
            'reason_category'    => 'required|string|max:255',
            'reason_details'     => 'nullable|string',
            'feedback_text'      => 'nullable|string',
            'initiated_by'       => 'required|in:employee,employer',
        ]);

        $exit = $this->exitRepository->storeExit($validated, auth()->id(), $tenantId);

        return redirect()->route('hrms.exits.index')
            ->with('success', "Exit case created for {$exit->employee->full_name}.");
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->initiate($request);
    }

    public function approve(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'approved_lwd'          => 'required|date',
            'notice_action'         => 'required|in:serve,waive,recover',
            'notice_shortfall_days' => 'nullable|integer|min:0',
            'hr_remarks'            => 'nullable|string',
        ]);

        $this->exitRepository->approveHr($exit, $validated, auth()->id(), $tenantId);

        return redirect()->back()->with('success', 'Exit request approved by HR. Clearance items generated.');
    }

    public function approveManager(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $this->authorize('approveManager', $exit);

        $validated = $request->validate([
            'manager_recommended_lwd' => 'nullable|date',
            'manager_remarks'         => 'nullable|string',
        ]);

        $this->exitRepository->approveManager($exit, $validated, auth()->id());

        return redirect()->back()->with('success', 'Exit request endorsed by manager and routed to HR for final sign-off.');
    }

    public function approveHr(Request $request, EmployeeExit $exit): RedirectResponse
    {
        return $this->approve($request, $exit);
    }

    public function reject(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $this->authorize('reject', $exit);

        $request->validate(['reason' => 'nullable|string']);
        $this->exitRepository->rejectExit($exit, $request->input('reason'));

        return redirect()->back()->with('success', 'Exit request rejected.');
    }

    public function updateClearance(Request $request, EmployeeExitClearance $clearance): RedirectResponse
    {
        $validated = $request->validate([
            'status'           => 'required|in:cleared,pending,waived,issue_found',
            'remarks'          => 'nullable|string',
            'deduction_amount' => 'nullable|numeric|min:0',
            'recovery_amount'  => 'nullable|numeric|min:0',
        ]);

        $validated['recovery_amount'] = $validated['recovery_amount'] ?? ($validated['deduction_amount'] ?? 0);

        $this->exitRepository->clearItem($clearance, $validated, auth()->id());

        return redirect()->back()->with('success', "Clearance item '{$clearance->item_name}' updated.");
    }

    public function clearItem(Request $request, EmployeeExitClearance $item): RedirectResponse
    {
        return $this->updateClearance($request, $item);
    }

    public function updateDepartmentClearances(Request $request, EmployeeExit $exit, string $department): RedirectResponse
    {
        $items = $request->input('items', []);
        $userId = auth()->id();

        DB::transaction(function () use ($items, $userId, $exit) {
            foreach ($items as $itemId => $itemData) {
                $c = EmployeeExitClearance::where('employee_exit_id', $exit->id)->find($itemId);
                if ($c) {
                    $status = $itemData['status'] ?? $c->status;
                    $deduction = isset($itemData['deduction_amount']) ? (float) $itemData['deduction_amount'] : ($c->deduction_amount ?? 0);
                    $remarks = $itemData['remarks'] ?? $c->remarks;

                    $c->update([
                        'status'           => $status,
                        'deduction_amount' => $deduction,
                        'recovery_amount'  => $deduction,
                        'remarks'          => $remarks,
                        'cleared_by'       => $userId,
                        'cleared_at'       => in_array($status, ['cleared', 'waived']) ? now() : null,
                    ]);
                }
            }
        });

        $this->exitRepository->recalculateFnf($exit);

        return redirect()->back()->with('success', "Clearances for department updated successfully.");
    }

    public function storeAdhocExitClearance(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $validated = $request->validate([
            'department'         => 'required|string|max:100',
            'item_name'          => 'required|string|max:255',
            'remarks'            => 'nullable|string',
            'deduction_amount'   => 'nullable|numeric|min:0',
        ]);

        $tenantId = $exit->tenant_id ?: (tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id());

        EmployeeExitClearance::create([
            'tenant_id'        => $tenantId,
            'employee_exit_id' => $exit->id,
            'department'       => $validated['department'],
            'item_name'        => $validated['item_name'],
            'status'           => 'pending',
            'remarks'          => $validated['remarks'] ?? null,
            'deduction_amount' => $validated['deduction_amount'] ?? 0.00,
        ]);

        $this->exitRepository->recalculateFnf($exit);

        return redirect()->back()->with('success', "Ad-hoc clearance item '{$validated['item_name']}' added.");
    }

    public function destroyExitClearance(EmployeeExitClearance $clearance): RedirectResponse
    {
        $exit = $clearance->employeeExit;
        $name = $clearance->item_name;
        $clearance->delete();

        if ($exit) {
            $this->exitRepository->recalculateFnf($exit);
        }

        return redirect()->back()->with('success', "Clearance item '{$name}' removed.");
    }

    public function returnAssetDirect(Request $request, EmployeeExit $exit, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'condition_on_return' => 'required|string|in:new,good,fair,damaged,scrapped',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $allocation = AssetAllocation::where('asset_id', $asset->id)
            ->where('employee_id', $exit->employee_id)
            ->whereNull('returned_at')
            ->first();

        DB::transaction(function () use ($asset, $allocation, $validated) {
            $newStatus = match ($validated['condition_on_return']) {
                'damaged'  => 'maintenance',
                'scrapped' => 'scrapped',
                default    => 'available',
            };

            if ($allocation) {
                $allocation->update([
                    'returned_at'      => now(),
                    'return_condition' => $validated['condition_on_return'],
                    'notes'            => $validated['notes'] ?? $allocation->notes,
                ]);
            }

            $asset->update([
                'status'               => $newStatus,
                'condition'            => $validated['condition_on_return'],
                'assigned_employee_id' => null,
                'allocated_at'         => null,
                'expected_return_date' => null,
            ]);
        });

        $this->exitRepository->recalculateFnf($exit);

        return redirect()->back()->with('success', "Asset '{$asset->name}' marked as returned.");
    }

    public function recalculateFnF(EmployeeExit $exit): RedirectResponse
    {
        $this->exitRepository->recalculateFnf($exit);

        return redirect()->back()->with('success', 'FnF Settlement values recalculated based on current leave, attendance, and asset clearances.');
    }

    public function finalizeFnF(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $validated = $request->validate([
            'unpaid_salary'           => 'required|numeric|min:0',
            'leave_encashment_amount' => 'required|numeric|min:0',
            'gratuity_amount'         => 'required|numeric|min:0',
            'bonus_amount'            => 'required|numeric|min:0',
            'other_earnings'          => 'nullable|numeric|min:0',
            'notice_recovery_amount'  => 'required|numeric|min:0',
            'asset_recovery_amount'   => 'required|numeric|min:0',
            'loan_deduction_amount'   => 'required|numeric|min:0',
            'tax_deduction_amount'    => 'required|numeric|min:0',
            'other_deductions'        => 'nullable|numeric|min:0',
            'remarks'                 => 'nullable|string',
        ]);

        $this->exitRepository->saveFnf($exit, $validated);

        return redirect()->back()->with('success', 'FnF settlement finalized and saved successfully.');
    }

    public function saveFnf(Request $request, EmployeeExit $exit): RedirectResponse
    {
        return $this->finalizeFnF($request, $exit);
    }

    public function viewDocument(EmployeeExitDocument $document): View
    {
        $data = [
            'document' => $document,
            'exit'     => $document->employeeExit,
            'employee' => $document->employee,
            'company'  => $document->employee?->company ?? \App\Domains\HRMS\Models\Company::first(),
        ];

        return view('modules.hrms.employees.exits.documents.custom_template_preview', $data);
    }

    public function viewRelievingLetter(EmployeeExit $exit): View
    {
        $doc = $this->docService->generateRelievingLetter($exit);
        $employee = $exit->employee;
        $company = $employee?->company ?: \App\Domains\HRMS\Models\Company::first();

        return view('modules.hrms.employees.exits.documents.relieving-letter', compact('exit', 'employee', 'company', 'doc'));
    }

    public function viewExperienceCertificate(EmployeeExit $exit): View
    {
        $doc = $this->docService->generateExperienceCertificate($exit);
        $employee = $exit->employee;
        $company = $employee?->company ?: \App\Domains\HRMS\Models\Company::first();

        return view('modules.hrms.employees.exits.documents.experience-certificate', compact('exit', 'employee', 'company', 'doc'));
    }

    public function viewNocCertificate(EmployeeExit $exit): View
    {
        $doc = $this->docService->generateNocCertificate($exit);
        $employee = $exit->employee;
        $company = $employee?->company ?: \App\Domains\HRMS\Models\Company::first();

        return view('modules.hrms.employees.exits.documents.noc-certificate', compact('exit', 'employee', 'company', 'doc'));
    }

    public function viewFnFStatement(EmployeeExit $exit): View
    {
        $employee = $exit->employee;
        $company = $employee?->company ?: \App\Domains\HRMS\Models\Company::first();
        $fnf = $exit->fnfSettlement ?: $this->fnfService->saveSettlement($exit, $this->fnfService->calculateFnF($exit));

        return view('modules.hrms.employees.exits.documents.fnf-statement', compact('exit', 'employee', 'company', 'fnf'));
    }
}
