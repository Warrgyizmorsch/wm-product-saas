<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\EmployeeExit;
use App\Domains\HRMS\Models\EmployeeExitClearance;
use App\Domains\HRMS\Models\EmployeeFnfSettlement;
use App\Domains\HRMS\Models\EmployeeExitDocument;
use App\Domains\HRMS\Models\ExitClearanceTemplate;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetAllocation;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Services\FnFCalculationService;
use App\Domains\HRMS\Services\ExitDocumentationService;
use App\Domains\HRMS\Services\ExitClearanceService;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeExitController extends Controller
{
    public function __construct(
        private readonly FnFCalculationService $fnfService,
        private readonly ExitDocumentationService $docService,
        private readonly ExitClearanceService $clearanceService
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
                'employee.company',
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
        $selectedCompanyId = $request->input('company_id');
        $sortBy = $request->input('sort_by', 'created_at');
        $sortOrder = $request->input('sort_order', 'desc');

        if ($departmentId) {
            $exitsQuery->whereHas('employee', fn($q) => $q->where('department_id', $departmentId));
        }

        if ($selectedCompanyId) {
            $exitsQuery->whereHas('employee', fn($q) => $q->where('company_id', $selectedCompanyId));
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

        if ($activeTab === 'clearances') {
            foreach ($exits as $exit) {
                $this->clearanceService->cleanDuplicateClearancesForExit($exit);
                $exit->load('clearances.clearedByUser');
            }
        }

        // 3. Dropdown helpers
        $departments = Department::where('status', true)->orderBy('name')->get();
        $companies = Company::orderBy('company_name')->get();

        return view('modules.hrms.employees.exits.index', compact(
            'exits',
            'departments',
            'companies',
            'activeTab',
            'search',
            'statusFilter',
            'departmentId',
            'selectedCompanyId',
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

        // Auto-generate dynamic clearance items from company/tenant templates
        $this->clearanceService->generateClearancesForExit($exit, $tenantId);

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

    /**
     * Store a new clearance template item.
     */
    public function storeClearanceTemplate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => 'nullable|exists:companies,id',
            'clearance_category' => 'required|string|max:100',
            'category_name' => 'required|string|max:150',
            'item_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'is_mandatory' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        // Normalize category slug
        $categoryKey = \Illuminate\Support\Str::slug($validated['clearance_category'], '_');

        ExitClearanceTemplate::create([
            'tenant_id' => $tenantId,
            'company_id' => $validated['company_id'] ?: null,
            'clearance_category' => $categoryKey,
            'category_name' => trim($validated['category_name']),
            'item_name' => trim($validated['item_name']),
            'description' => $validated['description'] ?? null,
            'is_mandatory' => $request->boolean('is_mandatory', true),
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => true,
        ]);

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $validated['company_id'] ?? null])->with('success', "Clearance checklist point '{$validated['item_name']}' created successfully.");
    }

    /**
     * Update an existing clearance template item.
     */
    public function updateClearanceTemplate(Request $request, ExitClearanceTemplate $template): RedirectResponse
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

        $categoryKey = \Illuminate\Support\Str::slug($validated['clearance_category'], '_');

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

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $template->company_id])->with('success', "Clearance checklist point '{$template->item_name}' updated successfully.");
    }

    /**
     * Delete a clearance template item.
     */
    public function destroyClearanceTemplate(ExitClearanceTemplate $template): RedirectResponse
    {
        $name = $template->item_name;
        $companyId = $template->company_id;
        $template->delete();

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])->with('success', "Clearance point '{$name}' removed from policy.");
    }

    /**
     * Reset templates back to the system defaults.
     */
    public function resetClearanceTemplates(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $companyId = $request->input('company_id') ? (int) $request->input('company_id') : null;

        $this->clearanceService->resetTemplatesToDefaults($tenantId, $companyId);

        return redirect()->route('hrms.offboarding-policies.index', ['company_id' => $companyId])->with('success', "Clearance checklist policies reset to standard 12 default items.");
    }

    /**
     * Add an ad-hoc custom clearance item to an ongoing exit case.
     */
    public function storeAdhocExitClearance(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $validated = $request->validate([
            'clearance_category' => 'required|string|max:100',
            'item_name' => 'required|string|max:255',
            'remarks' => 'nullable|string|max:500',
            'deduction_amount' => 'nullable|numeric|min:0',
        ]);

        $this->clearanceService->addAdhocClearanceItem($exit, $validated);

        return redirect()->route('hrms.exits.index', ['tab' => 'clearances'])->with('success', "Custom clearance item '{$validated['item_name']}' added to {$exit->employee->full_name}'s checklist.");
    }

    /**
     * Remove an ad-hoc clearance item from an exit case.
     */
    public function destroyExitClearance(EmployeeExitClearance $clearance): RedirectResponse
    {
        $exit = $clearance->exit;
        $itemName = $clearance->item_name;
        $clearance->delete();

        // Re-calculate FnF
        if ($exit) {
            $computedFnF = $this->fnfService->calculateFnF($exit);
            $this->fnfService->saveSettlement($exit, $computedFnF);
        }

        return redirect()->route('hrms.exits.index', ['tab' => 'clearances'])->with('success', "Clearance item '{$itemName}' removed.");
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
            return view('modules.hrms.employees.exits.documents.relieving-letter', compact('document'));
        } elseif ($document->document_type === 'experience_certificate') {
            return view('modules.hrms.employees.exits.documents.experience-certificate', compact('document'));
        } elseif ($document->document_type === 'noc_certificate') {
            return view('modules.hrms.employees.exits.documents.noc-certificate', compact('document'));
        }

        return view('modules.hrms.employees.exits.documents.relieving-letter', compact('document'));
    }

    public function viewFnFStatement(Request $request, EmployeeExit $exit): View
    {
        $exit->load(['employee.company', 'employee.designation', 'employee.department', 'fnfSettlement']);
        $settlement = $exit->fnfSettlement;
        
        return view('modules.hrms.employees.exits.documents.fnf-statement', compact('exit', 'settlement'));
    }

    public function viewRelievingLetter(Request $request, EmployeeExit $exit): View
    {
        $exit->load(['employee.company', 'employee.designation', 'employee.department']);
        $document = $exit->documents()->where('document_type', 'relieving_letter')->first();
        if (!$document) {
            $document = $this->docService->generateRelievingLetter($exit);
        }
        return view('modules.hrms.employees.exits.documents.relieving-letter', compact('document', 'exit'));
    }

    public function viewExperienceCertificate(Request $request, EmployeeExit $exit): View
    {
        $exit->load(['employee.company', 'employee.designation', 'employee.department']);
        $document = $exit->documents()->where('document_type', 'experience_certificate')->first();
        if (!$document) {
            $document = $this->docService->generateExperienceCertificate($exit);
        }
        return view('modules.hrms.employees.exits.documents.experience-certificate', compact('document', 'exit'));
    }

    public function viewNocCertificate(Request $request, EmployeeExit $exit): View
    {
        $exit->load(['employee.company', 'employee.designation', 'employee.department', 'clearances.clearedByUser']);
        $document = $exit->documents()->where('document_type', 'noc_certificate')->first();
        if (!$document) {
            $document = $this->docService->generateNocCertificate($exit);
        }
        return view('modules.hrms.employees.exits.documents.noc-certificate', compact('document', 'exit'));
    }
}
