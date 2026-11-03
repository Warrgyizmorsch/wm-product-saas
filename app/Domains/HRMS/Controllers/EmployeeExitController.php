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
            'separation_type'    => 'required|string|in:resignation,termination,retirement,absconding,death,layoff,contract_end',
            'resignation_date'   => 'required|date',
            'preferred_lwd'      => 'nullable|date|after_or_equal:resignation_date',
            'notice_period_days' => 'nullable|integer|min:0',
            'reason_category'    => 'required|string|max:255',
            'reason_details'     => 'nullable|string',
            'feedback_text'      => 'nullable|string',
            'initiated_by'       => 'nullable|in:employee,employer',
        ]);

        $validated['initiated_by'] = $validated['initiated_by'] ?? 'employee';

        $exit = $this->exitRepository->storeExit($validated, auth()->id(), $tenantId);

        if ($validated['initiated_by'] === 'employee' || !auth()->user()->can('viewAny', EmployeeExit::class)) {
            return redirect()->back()->with('success', "Resignation request submitted successfully.");
        }

        return redirect()->route('hrms.exits.index')
            ->with('success', "Exit case created for {$exit->employee->full_name}.");
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->initiate($request);
    }

    public function approve(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $this->authorize('approve', $exit);

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
            'status'           => 'required|in:cleared,pending,waived,issue_found,issues_found',
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
        $items = $request->input('clearances') ?: $request->input('items', []);
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

        $this->clearanceService->checkAllClearancesCompleted($exit);
        $this->exitRepository->recalculateFnf($exit);

        return redirect()->back()->with('success', "Clearances for department updated successfully.");
    }

    public function updateAllExitClearances(Request $request, EmployeeExit $exit): RedirectResponse
    {
        return $this->updateDepartmentClearances($request, $exit, 'all');
    }

    public function storeAdhocExitClearance(Request $request, EmployeeExit $exit): RedirectResponse
    {
        $validated = $request->validate([
            'department'         => 'nullable|string|max:100',
            'clearance_category' => 'nullable|string|max:100',
            'item_name'          => 'required|string|max:255',
            'remarks'            => 'nullable|string',
            'deduction_amount'   => 'nullable|numeric|min:0',
        ]);

        $tenantId = $exit->tenant_id ?: (tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id());
        $department = $validated['department'] ?? ($validated['clearance_category'] ?? 'hr_operations');

        EmployeeExitClearance::create([
            'tenant_id'        => $tenantId,
            'employee_exit_id' => $exit->id,
            'department'       => $department,
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
            'condition'           => 'nullable|string|in:good,fair,damaged,lost,scrapped,new',
            'condition_on_return' => 'nullable|string|in:good,fair,damaged,lost,scrapped,new',
            'damage_deduction'    => 'nullable|numeric|min:0',
            'remarks'             => 'nullable|string|max:1000',
            'notes'               => 'nullable|string|max:1000',
        ]);

        $condition = $validated['condition'] ?? ($validated['condition_on_return'] ?? 'good');
        $remarks = $validated['remarks'] ?? ($validated['notes'] ?? null);
        $damageDeduction = floatval($validated['damage_deduction'] ?? 0);

        $allocation = AssetAllocation::where('asset_id', $asset->id)
            ->where('employee_id', $exit->employee_id)
            ->whereNull('returned_at')
            ->first();

        DB::transaction(function () use ($asset, $allocation, $condition, $remarks, $exit, $damageDeduction) {
            $newStatus = match ($condition) {
                'damaged'          => 'maintenance',
                'lost', 'scrapped' => 'scrapped',
                default            => 'available',
            };

            if ($allocation) {
                $allocation->update([
                    'returned_at'      => now(),
                    'return_condition' => $condition,
                    'notes'            => $remarks ?: $allocation->notes,
                ]);
            }

            $asset->update([
                'status'               => $newStatus,
                'condition'            => $condition,
                'assigned_employee_id' => null,
                'allocated_at'         => null,
                'expected_return_date' => null,
            ]);

            // If damage/loss penalty is applied, create or update clearance record in IT Assets
            if ($damageDeduction > 0) {
                $tenantId = $exit->tenant_id ?: (tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id());
                EmployeeExitClearance::updateOrCreate(
                    [
                        'employee_exit_id' => $exit->id,
                        'department'       => 'it_assets',
                        'item_name'        => "Hardware Recovery: {$asset->name}",
                    ],
                    [
                        'tenant_id'        => $tenantId,
                        'status'           => 'issues_found',
                        'deduction_amount' => $damageDeduction,
                        'recovery_amount'  => $damageDeduction,
                        'remarks'          => "Asset condition: {$condition}. " . ($remarks ?: 'Deduction applied on asset return.'),
                        'cleared_by'       => auth()->id(),
                    ]
                );
            } else {
                // If returned in good/fair condition with 0 deduction, mark any IT clearance recovery for this asset as cleared
                EmployeeExitClearance::where('employee_exit_id', $exit->id)
                    ->where('department', 'it_assets')
                    ->where('item_name', "Hardware Recovery: {$asset->name}")
                    ->update([
                        'status'           => 'cleared',
                        'deduction_amount' => 0.00,
                        'recovery_amount'  => 0.00,
                        'remarks'          => "Asset returned in {$condition} condition.",
                        'cleared_by'       => auth()->id(),
                        'cleared_at'       => now(),
                    ]);
            }
        });

        $this->clearanceService->checkAllClearancesCompleted($exit);
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
            'payment_method'      => 'nullable|string|max:100',
            'payment_mode'        => 'nullable|string|max:100',
            'settlement_channel'  => 'required|string|in:monthly_payroll,off_cycle',
            'bank_account_id'     => 'nullable|integer|exists:chart_of_accounts,id',
            'payout_date'         => 'nullable|date',
            'payment_reference'   => 'nullable|string|max:255',
            'notes'               => 'nullable|string',
            'override_clearances' => 'nullable|boolean',
            'override_reason'     => 'nullable|string',
            'unpaid_salary'       => 'nullable|numeric|min:0',
            'leave_encashment_amount' => 'nullable|numeric|min:0',
            'gratuity_amount'     => 'nullable|numeric|min:0',
            'bonus_amount'        => 'nullable|numeric|min:0',
            'other_earnings'      => 'nullable|numeric|min:0',
            'notice_recovery_amount' => 'nullable|numeric|min:0',
            'asset_recovery_amount'  => 'nullable|numeric|min:0',
            'loan_deduction_amount'  => 'nullable|numeric|min:0',
            'tax_deduction_amount'   => 'nullable|numeric|min:0',
            'other_deductions'    => 'nullable|numeric|min:0',
        ]);

        if ($exit->clearances()->where('status', 'pending')->exists() && !$request->boolean('override_clearances')) {
            return redirect()->back()->with('error', 'Cannot finalize FnF settlement: Pending clearance items exist. Please complete departmental clearances or check the clearance override option.');
        }

        $settlement = $exit->fnfSettlement ?: $this->fnfService->saveSettlement($exit, $this->fnfService->calculateFnF($exit));

        if (!empty($validated['override_reason'])) {
            $validated['notes'] = trim(($validated['notes'] ?? '') . ' [Clearance Override: ' . $validated['override_reason'] . ']');
        }

        $channel = $validated['settlement_channel'] ?? 'monthly_payroll';

        // OPTION A: Add in Payroll (Queued for regular Monthly Payroll run)
        if ($channel === 'monthly_payroll') {
            $updateData = [
                'status'             => 'approved',
                'settlement_channel' => 'monthly_payroll',
                'payment_method'     => 'Monthly Payroll',
                'notes'              => $validated['notes'] ?? $settlement->notes,
            ];

            if (isset($validated['unpaid_salary'])) {
                $updateData['unpaid_salary_amount'] = (float) $validated['unpaid_salary'];
            }
            if (isset($validated['leave_encashment_amount'])) {
                $updateData['leave_encashment_amount'] = (float) $validated['leave_encashment_amount'];
            }
            if (isset($validated['gratuity_amount'])) {
                $updateData['gratuity_amount'] = (float) $validated['gratuity_amount'];
            }
            if (isset($validated['bonus_amount'])) {
                $updateData['bonus_amount'] = (float) $validated['bonus_amount'];
            }
            if (isset($validated['other_earnings'])) {
                $updateData['other_earnings'] = (float) $validated['other_earnings'];
            }
            if (isset($validated['notice_recovery_amount'])) {
                $updateData['notice_shortfall_recovery'] = (float) $validated['notice_recovery_amount'];
            }
            if (isset($validated['asset_recovery_amount'])) {
                $updateData['asset_damage_recovery'] = (float) $validated['asset_recovery_amount'];
            }
            if (isset($validated['other_deductions'])) {
                $updateData['other_deductions'] = (float) $validated['other_deductions'];
            }

            if (isset($validated['unpaid_salary']) || isset($validated['leave_encashment_amount']) || isset($validated['gratuity_amount']) || isset($validated['notice_recovery_amount'])) {
                $earnings = ($updateData['unpaid_salary_amount'] ?? $settlement->unpaid_salary_amount)
                    + ($updateData['leave_encashment_amount'] ?? $settlement->leave_encashment_amount)
                    + ($updateData['gratuity_amount'] ?? $settlement->gratuity_amount)
                    + ($updateData['bonus_amount'] ?? $settlement->bonus_amount)
                    + ($updateData['other_earnings'] ?? $settlement->other_earnings);
                $deductions = ($updateData['notice_shortfall_recovery'] ?? $settlement->notice_shortfall_recovery)
                    + ($updateData['asset_damage_recovery'] ?? $settlement->asset_damage_recovery)
                    + (float) ($settlement->unsettled_advances_recovery ?? 0)
                    + ($updateData['other_deductions'] ?? $settlement->other_deductions);
                $updateData['total_earnings'] = round($earnings, 2);
                $updateData['total_deductions'] = round($deductions, 2);
                $updateData['net_payable_amount'] = round(max(0, $earnings - $deductions), 2);
            }

            $settlement->update($updateData);
            $exit->update(['status' => 'approved']);

            return redirect()->route('hrms.exits.index', ['tab' => 'fnf'])
                ->with('success', "FnF settlement for {$exit->employee->full_name} is confirmed and queued for the regular Monthly Payroll run. Bank account and ledger disbursement will occur when monthly payroll is released.");
        }

        // OPTION B: Immediate Payout by Accounting (Off-Cycle)
        if (empty($validated['bank_account_id'])) {
            return redirect()->back()->with('error', 'Please select the bank or cash account the immediate settlement is paid from.');
        }

        $paymentMode = $validated['payment_method'] ?? ($validated['payment_mode'] ?? 'Bank Transfer');
        $validated['payment_mode'] = $paymentMode;
        $validated['payment_method'] = $paymentMode;
        $validated['paid_at'] = !empty($validated['payout_date']) ? Carbon::parse($validated['payout_date']) : now();

        try {
            $accounting = app(\App\Domains\Accounting\Services\PayrollAccountingService::class);
            $accounting->postFnFSettlementPayoutJournal($settlement, (int) $validated['bank_account_id'], $validated['paid_at']->toDateString());
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', 'Accounting payout refused: ' . $e->getMessage());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('FnF accounting payout posting warning: ' . $e->getMessage());
        }

        $this->exitRepository->settleFnf($settlement, $validated, auth()->id());

        // Pre-generate all exit certificates upon immediate settlement
        $this->docService->generateRelievingLetter($exit);
        $this->docService->generateExperienceCertificate($exit);
        $this->docService->generateNocCertificate($exit);

        return redirect()->route('hrms.exits.index', ['tab' => 'documents'])
            ->with('success', "FnF settlement immediately paid and posted in Accounting for {$exit->employee->full_name}. Relieving letter & certificates generated.");
    }

    public function saveFnf(Request $request, EmployeeExit $exit): RedirectResponse
    {
        return $this->finalizeFnF($request, $exit);
    }

    public function viewDocument(EmployeeExitDocument $document): View
    {
        $exit = $document->employeeExit;
        if ($exit) {
            $this->authorize('view', $exit);
        }

        $employee = $document->employee ?? $exit?->employee;
        $company = $employee?->company ?: \App\Domains\HRMS\Models\Company::first();

        $data = [
            'document'         => $document,
            'doc'              => $document,
            'exit'             => $exit,
            'employee'         => $employee,
            'company'          => $company,
            'title'            => ucfirst(str_replace('_', ' ', $document->document_type)),
            'employeeName'     => $employee?->full_name,
            'renderedContent'  => is_array($document->content_data) ? ($document->content_data['body'] ?? json_encode($document->content_data)) : $document->content_data,
        ];

        return match ($document->document_type) {
            'relieving_letter'       => view('modules.hrms.employees.exits.documents.relieving-letter', $data),
            'experience_certificate' => view('modules.hrms.employees.exits.documents.experience-certificate', $data),
            'noc_certificate'        => view('modules.hrms.employees.exits.documents.noc-certificate', $data),
            'fnf_statement'          => view('modules.hrms.employees.exits.documents.fnf-statement', array_merge($data, [
                'fnf' => $exit?->fnfSettlement,
                'settlement' => $exit?->fnfSettlement,
            ])),
            default                  => view('modules.hrms.employees.exits.documents.custom_template_preview', $data),
        };
    }

    public function viewRelievingLetter(EmployeeExit $exit): View
    {
        $this->authorize('view', $exit);

        $document = $this->docService->generateRelievingLetter($exit);
        $doc = $document;
        $employee = $exit->employee;
        $company = $employee?->company ?: \App\Domains\HRMS\Models\Company::first();

        return view('modules.hrms.employees.exits.documents.relieving-letter', compact('exit', 'employee', 'company', 'document', 'doc'));
    }

    public function viewExperienceCertificate(EmployeeExit $exit): View
    {
        $this->authorize('view', $exit);

        $document = $this->docService->generateExperienceCertificate($exit);
        $doc = $document;
        $employee = $exit->employee;
        $company = $employee?->company ?: \App\Domains\HRMS\Models\Company::first();

        return view('modules.hrms.employees.exits.documents.experience-certificate', compact('exit', 'employee', 'company', 'document', 'doc'));
    }

    public function viewNocCertificate(EmployeeExit $exit): View
    {
        $this->authorize('view', $exit);

        $document = $this->docService->generateNocCertificate($exit);
        $doc = $document;
        $employee = $exit->employee;
        $company = $employee?->company ?: \App\Domains\HRMS\Models\Company::first();

        return view('modules.hrms.employees.exits.documents.noc-certificate', compact('exit', 'employee', 'company', 'document', 'doc'));
    }

    public function viewFnFStatement(EmployeeExit $exit): View
    {
        $this->authorize('view', $exit);

        $employee = $exit->employee;
        $company = $employee?->company ?: \App\Domains\HRMS\Models\Company::first();
        $settlement = $exit->fnfSettlement ?: $this->fnfService->saveSettlement($exit, $this->fnfService->calculateFnF($exit));
        $fnf = $settlement;

        return view('modules.hrms.employees.exits.documents.fnf-statement', compact('exit', 'employee', 'company', 'fnf', 'settlement'));
    }
}
