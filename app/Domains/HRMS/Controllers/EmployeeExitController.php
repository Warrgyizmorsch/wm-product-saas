<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Models\EmployeeFnfSettlement;
use App\Domains\HRMS\Models\EmployeeExitDocument;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetAllocation;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Services\FnFCalculationService;
use App\Domains\HRMS\Services\ExitDocumentationService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeExitController extends Controller
{
    public function __construct(
        private readonly FnFCalculationService $fnfService,
        private readonly ExitDocumentationService $docService
    ) {}

    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $activeTab = $request->input('tab', 'exits');
        $search = $request->input('search');
        $statusFilter = $request->input('status');

        // 1. Stats
        $baseQuery = EmployeeExit::query()->where('tenant_id', $tenantId);

        $activeExitsCount = (clone $baseQuery)->whereIn('status', ['pending_manager', 'pending_hr', 'approved', 'in_clearance'])->count();
        $inClearanceCount = (clone $baseQuery)->where('status', 'in_clearance')->count();
        $pendingFnfCount = (clone $baseQuery)->whereHas('fnfSettlement', fn($q) => $q->whereIn('status', ['draft', 'approved']))->count();
        $settledThisMonthCount = (clone $baseQuery)->where('status', 'settled')
            ->whereMonth('updated_at', Carbon::today()->month)
            ->whereYear('updated_at', Carbon::today()->year)
            ->count();
        $settledExitsCount = (clone $baseQuery)->where(function($q) {
            $q->where('status', 'settled')
              ->orWhereHas('fnfSettlement', fn($fq) => $fq->where('status', 'paid'));
        })->count();

        // 2. Query Exits
        $exitsQuery = EmployeeExit::query()
            ->where('tenant_id', $tenantId)
            ->with([
                'employee.department',
                'employee.designation',
                'employee.reportingManager',
                'employee.assets',
                'clearances.clearedByUser',
                'fnfSettlement',
                'documents'
            ]);

        if ($activeTab === 'documents') {
            $exitsQuery->where(function($q) {
                $q->where('status', 'settled')
                  ->orWhereHas('fnfSettlement', fn($fq) => $fq->where('status', 'paid'));
            });
        }

        if ($search) {
            $exitsQuery->whereHas('employee', function($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%")
                  ->orWhere('personal_email', 'like', "%{$search}%");
            });
        }

        if ($statusFilter) {
            $exitsQuery->where('status', $statusFilter);
        }

        $departmentId = $request->input('department_id');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        if ($departmentId) {
            $exitsQuery->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
        }

        if ($sortBy === 'lwd') {
            $exitsQuery->orderBy('approved_lwd', $sortOrder === 'asc' ? 'asc' : 'desc')
                       ->orderBy('preferred_lwd', $sortOrder === 'asc' ? 'asc' : 'desc');
        } elseif ($sortBy === 'employee') {
            $exitsQuery->join('employees', 'employees.id', '=', 'employee_exits.employee_id')
                       ->orderBy('employees.full_name', $sortOrder === 'asc' ? 'asc' : 'desc')
                       ->select('employee_exits.*');
        } else {
            $exitsQuery->orderBy('created_at', $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        $exits = $exitsQuery->paginate(15)->withQueryString();

        // 3. Dropdown helpers
        $employees = Employee::where('tenant_id', $tenantId)
            ->where('status', true)
            ->orderBy('full_name')
            ->get();
        $departments = Department::where('status', true)->orderBy('name')->get();

        return view('modules.hrms.exits.index', compact(
            'exits',
            'employees',
            'departments',
            'activeTab',
            'search',
            'statusFilter',
            'departmentId',
            'sortBy',
            'sortOrder',
            'activeExitsCount',
            'inClearanceCount',
            'pendingFnfCount',
            'settledThisMonthCount',
            'settledExitsCount'
        ));
    }

    public function initiate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'separation_type' => 'required|string|in:resignation,termination,retirement,layoff,contract_end,absconding',
            'resignation_date' => 'required|date',
            'preferred_lwd' => 'nullable|date|after_or_equal:resignation_date',
            'notice_period_days' => 'required|integer|min:0|max:180',
            'reason_category' => 'nullable|string|max:255',
            'reason_details' => 'nullable|string|max:2000',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $employee = Employee::findOrFail($validated['employee_id']);

        // Calculate expected LWD
        $resignationDate = Carbon::parse($validated['resignation_date']);
        $calculatedLwd = $validated['preferred_lwd'] 
            ? Carbon::parse($validated['preferred_lwd']) 
            : $resignationDate->copy()->addDays((int) $validated['notice_period_days']);

        $exit = EmployeeExit::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'separation_type' => $validated['separation_type'],
            'resignation_date' => $validated['resignation_date'],
            'preferred_lwd' => $calculatedLwd->format('Y-m-d'),
            'approved_lwd' => $calculatedLwd->format('Y-m-d'),
            'notice_period_days' => $validated['notice_period_days'],
            'notice_shortfall_days' => 0,
            'notice_action' => 'serve',
            'reason_category' => $validated['reason_category'] ?? 'Career Growth',
            'reason_details' => $validated['reason_details'] ?? null,
            'status' => 'in_clearance',
            'initiated_by' => (auth()->user() && auth()->user()->id === $employee->user_id) ? 'employee' : 'hr',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Auto-generate standard multi-department clearance items
        $standardChecklist = [
            ['department' => 'it', 'item_name' => 'Hardware Asset Recovery (Laptop/Accessories)'],
            ['department' => 'it', 'item_name' => 'Email, Slack & ERP System Logins Deactivation'],
            ['department' => 'it', 'item_name' => 'Cloud Data Backup & File Handover'],
            ['department' => 'admin', 'item_name' => 'Company Physical ID Card & Access Badge Handover'],
            ['department' => 'admin', 'item_name' => 'Office Keys, Drawer Keys & Parking Tag Handover'],
            ['department' => 'finance', 'item_name' => 'Reconcile Open Cash Advances & Loan Accounts'],
            ['department' => 'finance', 'item_name' => 'Verify Pending Travel & Expense Reimbursements'],
            ['department' => 'finance', 'item_name' => 'Notice Period Shortfall / Buyout Verification'],
            ['department' => 'hr', 'item_name' => 'Exit Interview & Feedback Questionnaire Completed'],
            ['department' => 'hr', 'item_name' => 'PF, Gratuity & Pension Settlement Verification'],
            ['department' => 'manager', 'item_name' => 'Knowledge Transfer (KT) & Task Handover Sign-off'],
            ['department' => 'manager', 'item_name' => 'Client Contacts, Repo & Credentials Handover'],
        ];

        foreach ($standardChecklist as $item) {
            EmployeeExitClearance::create([
                'tenant_id' => $tenantId,
                'employee_exit_id' => $exit->id,
                'department' => $item['department'],
                'item_name' => $item['item_name'],
                'status' => 'pending',
            ]);
        }

        // Update employee stage
        $employee->update(['employee_stage' => 'Notice Period']);

        // Generate draft FnF
        $computedFnF = $this->fnfService->calculateFnF($exit);
        $this->fnfService->saveSettlement($exit, $computedFnF);

        if ($request->has('redirect_back') || str_contains(url()->previous(), '/hrms/employees/')) {
            return redirect()->back()->with('success', "Exit / Resignation initiated successfully for {$employee->full_name}. Multi-department clearance checklist created.");
        }

        return redirect()->route('hrms.exits.index', ['tab' => 'exits'])->with('success', "Exit initiated successfully for {$employee->full_name}. Multi-department clearance checklist created.");
    }

    public function approve(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $validated = $request->validate([
            'approved_lwd' => 'required|date',
            'notice_action' => 'required|string|in:serve,recover,waive',
            'notice_shortfall_days' => 'nullable|integer|min:0',
        ]);

        $exit->update([
            'approved_lwd' => $validated['approved_lwd'],
            'notice_action' => $validated['notice_action'],
            'notice_shortfall_days' => $validated['notice_shortfall_days'] ?? 0,
            'status' => 'in_clearance',
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ]);

        // Re-calculate FnF
        $computedFnF = $this->fnfService->calculateFnF($exit);
        $this->fnfService->saveSettlement($exit, $computedFnF);

        return redirect()->back()->with('success', "Exit approved. Last working day set to {$exit->approved_lwd}.");
    }

    public function updateClearance(Request $request, EmployeeExitClearance $clearance): RedirectResponse
    {
        $validated = $request->validate([
            'status' => 'required|string|in:pending,cleared,waived,rejected,issues_found',
            'remarks' => 'nullable|string|max:500',
            'deduction_amount' => 'nullable|numeric|min:0',
        ]);

        $clearance->update([
            'status' => $validated['status'],
            'cleared_by' => auth()->id(),
            'cleared_at' => now(),
            'remarks' => $validated['remarks'] ?? null,
            'deduction_amount' => $validated['deduction_amount'] ?? 0.00,
        ]);

        // Re-calculate FnF to update asset deductions
        $exit = $clearance->exit;
        if ($exit) {
            $computedFnF = $this->fnfService->calculateFnF($exit);
            $this->fnfService->saveSettlement($exit, $computedFnF);
        }

        return redirect()->route('hrms.exits.index', ['tab' => 'clearances'])->with('success', "Clearance item '{$clearance->item_name}' updated successfully.");
    }

    public function updateDepartmentClearances(Request $request, EmployeeExit $exit, string $department): RedirectResponse
    {
        $validated = $request->validate([
            'clearances' => 'required|array',
            'clearances.*.status' => 'required|string|in:pending,cleared,waived,rejected,issues_found',
            'clearances.*.remarks' => 'nullable|string|max:500',
            'clearances.*.deduction_amount' => 'nullable|numeric|min:0',
        ]);

        foreach ($validated['clearances'] as $clearanceId => $itemData) {
            $clearance = $exit->clearances()->where('id', $clearanceId)->first();
            if ($clearance) {
                $clearance->update([
                    'status' => $itemData['status'],
                    'cleared_by' => auth()->id(),
                    'cleared_at' => now(),
                    'remarks' => $itemData['remarks'] ?? null,
                    'deduction_amount' => $itemData['deduction_amount'] ?? 0.00,
                ]);
            }
        }

        // Re-calculate FnF
        $computedFnF = $this->fnfService->calculateFnF($exit);
        $this->fnfService->saveSettlement($exit, $computedFnF);

        return redirect()->route('hrms.exits.index', ['tab' => 'clearances'])->with('success', "Clearance checklist saved successfully for {$exit->employee->full_name}.");
    }

    public function returnAssetDirect(Request $request, EmployeeExit $exit, Asset $asset): RedirectResponse
    {
        $validated = $request->validate([
            'condition' => 'required|string|in:good,damaged,lost',
            'damage_deduction' => 'nullable|numeric|min:0',
            'deduction_amount' => 'nullable|numeric|min:0',
            'remarks' => 'nullable|string|max:500',
        ]);

        $deduction = floatval($validated['damage_deduction'] ?? ($validated['deduction_amount'] ?? 0.00));

        $assetCondition = match ($validated['condition']) {
            'lost' => 'scrapped',
            'damaged' => 'damaged',
            'fair' => 'fair',
            default => 'good',
        };

        $newStatus = match ($validated['condition']) {
            'lost' => 'scrapped',
            'damaged' => 'maintenance',
            default => 'available',
        };

        // Update master asset record in main Asset module
        $asset->update([
            'assigned_employee_id' => null,
            'status' => $newStatus,
            'condition' => $assetCondition,
            'allocated_at' => null,
            'expected_return_date' => null,
            'notes' => trim(($asset->notes ?? '') . " [Returned from employee {$exit->employee->full_name} during exit on " . date('Y-m-d') . ". Condition: {$assetCondition}. Remarks: " . ($validated['remarks'] ?? 'None') . "]"),
        ]);

        // Find existing open allocation or record new closed allocation
        $openAllocation = AssetAllocation::where('asset_id', $asset->id)
            ->where('employee_id', $exit->employee_id)
            ->whereNull('returned_at')
            ->latest()
            ->first();

        if ($openAllocation) {
            $openAllocation->update([
                'returned_at' => now(),
                'return_condition' => $assetCondition,
                'notes' => trim(($openAllocation->notes ?? '') . ' [Returned during exit. Remarks: ' . ($validated['remarks'] ?? 'None') . ']'),
            ]);
        } else {
            AssetAllocation::create([
                'tenant_id' => $exit->tenant_id,
                'asset_id' => $asset->id,
                'employee_id' => $exit->employee_id,
                'allocated_at' => $asset->allocated_at ?: now(),
                'returned_at' => now(),
                'allocation_condition' => $asset->condition ?: 'good',
                'return_condition' => $assetCondition,
                'notes' => 'Returned during exit process. Remarks: ' . ($validated['remarks'] ?? 'None'),
            ]);
        }

        // If damaged or lost with deduction, link to IT clearance item
        if ($deduction > 0) {
            $itClearance = $exit->clearances()->where('department', 'it')->first();
            if ($itClearance) {
                $itClearance->update([
                    'status' => 'issues_found',
                    'deduction_amount' => $itClearance->deduction_amount + $deduction,
                    'remarks' => trim(($itClearance->remarks ?? '') . " [Asset {$asset->name} recovery fee: \${$deduction}]"),
                ]);
            }
        }

        // Re-calculate FnF
        $computedFnF = $this->fnfService->calculateFnF($exit);
        $this->fnfService->saveSettlement($exit, $computedFnF);

        return redirect()->back()->with('success', "Asset {$asset->name} marked as returned successfully.");
    }

    public function recalculateFnF(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $computedFnF = $this->fnfService->calculateFnF($exit);
        $this->fnfService->saveSettlement($exit, $computedFnF);

        return redirect()->back()->with('success', "Full & Final Settlement recalculated successfully.");
    }

    public function finalizeFnF(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $clearanceProgress = $exit->getClearanceProgressPercentage();
        $isClearanceIncomplete = $clearanceProgress < 100;

        $rules = [
            'payment_method' => 'required|string|max:100',
            'payment_reference' => 'nullable|string|max:255',
            'settlement_channel' => 'required|string|in:monthly_payroll,off_cycle',
            'notes' => 'nullable|string|max:1000',
        ];

        if ($isClearanceIncomplete) {
            $rules['override_clearances'] = 'accepted';
            $rules['override_reason'] = 'required|string|min:3|max:500';
        }

        $validated = $request->validate($rules, [
            'override_clearances.accepted' => 'You must check the authorization box to override pending clearances.',
            'override_reason.required' => 'Please state the reason for early settlement override.',
        ]);

        $settlement = $exit->fnfSettlement;
        if (!$settlement) {
            $computedFnF = $this->fnfService->calculateFnF($exit);
            $settlement = $this->fnfService->saveSettlement($exit, $computedFnF);
        }

        $notes = $validated['notes'] ?? '';
        if ($isClearanceIncomplete) {
            $notes = trim($notes . " [Early Settlement Override (Clearance: {$clearanceProgress}%): " . $validated['override_reason'] . " by Admin #" . auth()->id() . "]");
        }

        $settlement->update([
            'status' => 'paid',
            'settlement_channel' => $validated['settlement_channel'],
            'payment_method' => $validated['payment_method'],
            'payment_reference' => $validated['payment_reference'] ?? ('FNF-' . strtoupper(uniqid())),
            'paid_at' => now(),
            'notes' => $notes ?: null,
        ]);

        $exit->update(['status' => 'settled']);

        // Generate final documents
        $this->docService->generateRelievingLetter($exit);
        $this->docService->generateExperienceCertificate($exit);
        $this->docService->generateNocCertificate($exit);

        // Deactivate employee & user account
        $employee = $exit->employee;
        $employee->update([
            'status' => false,
            'employee_stage' => 'Exited',
        ]);

        if ($employee->user) {
            // Disable user password and login access
            $employee->user->update([
                'password' => bcrypt(uniqid('LOCKED_', true)),
            ]);
        }

        return redirect()->route('hrms.exits.index', ['tab' => 'fnf'])->with('success', "Full & Final Settlement completed for {$employee->full_name}. Relieving & Experience certificates generated, and user account deactivated.");
    }

    public function viewDocument(Request $request, EmployeeExitDocument $document): View
    {
        $document->load(['employee.company', 'exit']);
        
        if ($document->document_type === 'relieving_letter') {
            return view('modules.hrms.exits.documents.relieving-letter', compact('document'));
        } elseif ($document->document_type === 'experience_certificate') {
            return view('modules.hrms.exits.documents.experience-certificate', compact('document'));
        } elseif ($document->document_type === 'noc_certificate') {
            return view('modules.hrms.exits.documents.noc-certificate', compact('document'));
        }

        return view('modules.hrms.exits.documents.relieving-letter', compact('document'));
    }

    public function viewFnFStatement(Request $request, EmployeeExit $exit): View
    {
        $exit->load(['employee.company', 'employee.designation', 'employee.department', 'fnfSettlement']);
        $settlement = $exit->fnfSettlement;
        
        return view('modules.hrms.exits.documents.fnf-statement', compact('exit', 'settlement'));
    }

    public function viewRelievingLetter(Request $request, EmployeeExit $exit): View
    {
        $exit->load(['employee.company', 'employee.designation', 'employee.department']);
        $document = $exit->documents()->where('document_type', 'relieving_letter')->first();
        if (!$document) {
            $document = $this->docService->generateRelievingLetter($exit);
        }
        return view('modules.hrms.exits.documents.relieving-letter', compact('document', 'exit'));
    }

    public function viewExperienceCertificate(Request $request, EmployeeExit $exit): View
    {
        $exit->load(['employee.company', 'employee.designation', 'employee.department']);
        $document = $exit->documents()->where('document_type', 'experience_certificate')->first();
        if (!$document) {
            $document = $this->docService->generateExperienceCertificate($exit);
        }
        return view('modules.hrms.exits.documents.experience-certificate', compact('document', 'exit'));
    }

    public function viewNocCertificate(Request $request, EmployeeExit $exit): View
    {
        $exit->load(['employee.company', 'employee.designation', 'employee.department', 'clearances.clearedByUser']);
        $document = $exit->documents()->where('document_type', 'noc_certificate')->first();
        if (!$document) {
            $document = $this->docService->generateNocCertificate($exit);
        }
        return view('modules.hrms.exits.documents.noc-certificate', compact('document', 'exit'));
    }
}
