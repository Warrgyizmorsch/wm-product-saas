<?php

namespace App\Domains\HRMS\Controllers;

use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\ExpenseCategory;
use App\Domains\HRMS\Models\ExpensePolicy;
use App\Domains\HRMS\Models\TravelRequest;
use App\Domains\HRMS\Models\CashAdvance;
use App\Domains\HRMS\Models\ExpenseReport;
use App\Domains\HRMS\Models\ExpenseClaim;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;

class TravelExpenseController extends Controller
{
    public function index(Request $request): View
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();

        // 1. Resolve employee context
        $employee = null;
        if ($user && $user->email) {
            $employee = Employee::where('personal_email', $user->email)
                ->orWhere('office_email', $user->email)
                ->first();
        }

        // 2. Fetch lists for dropdowns
        $employees = Employee::where('status', true)->orderBy('full_name')->get();
        $categories = ExpenseCategory::where('tenant_id', $tenantId)->where('status', true)->orderBy('name')->get();
        $designations = Designation::where('status', true)->orderBy('name')->get();

        // 3. Fetch operational lists (Travel Requests, Advances, Reports)
        // If user is Admin, show all records; otherwise, show only employee's records
        $isAdmin = ($user->role ?? '') === 'admin';

        // 3. Query lists with sorting, searching, and filtering
        $travelSearch = $request->input('travel_search');
        $travelStatus = $request->input('travel_status');
        $travelSort = $request->input('travel_sort', 'newest');

        $travelQuery = TravelRequest::where('tenant_id', $tenantId)->with('employee');
        if ($travelSearch) {
            $travelQuery->where(function($q) use ($travelSearch) {
                $q->where('purpose', 'like', "%{$travelSearch}%")
                  ->orWhere('destination', 'like', "%{$travelSearch}%")
                  ->orWhereHas('employee', function($eq) use ($travelSearch) {
                      $eq->where('full_name', 'like', "%{$travelSearch}%");
                  });
            });
        }
        if ($travelStatus) {
            $travelQuery->where('status', $travelStatus);
        }
        $travelQuery->orderBy('created_at', $travelSort === 'oldest' ? 'asc' : 'desc');
        $travelRequests = $travelQuery->get();

        $advanceSearch = $request->input('advance_search');
        $advanceStatus = $request->input('advance_status');
        $advanceSort = $request->input('advance_sort', 'newest');

        $advanceQuery = CashAdvance::where('tenant_id', $tenantId)->with(['employee', 'travelRequest']);
        if ($advanceSearch) {
            $advanceQuery->where(function($q) use ($advanceSearch) {
                $q->where('purpose', 'like', "%{$advanceSearch}%")
                  ->orWhereHas('employee', function($eq) use ($advanceSearch) {
                      $eq->where('full_name', 'like', "%{$advanceSearch}%");
                  });
            });
        }
        if ($advanceStatus) {
            $advanceQuery->where('status', $advanceStatus);
        }
        $advanceQuery->orderBy('created_at', $advanceSort === 'oldest' ? 'asc' : 'desc');
        $cashAdvances = $advanceQuery->get();

        $reportSearch = $request->input('report_search');
        $reportStatus = $request->input('report_status');
        $reportSort = $request->input('report_sort', 'newest');

        $reportQuery = ExpenseReport::where('tenant_id', $tenantId)->with(['employee', 'claims.category']);
        if ($reportSearch) {
            $reportQuery->where(function($q) use ($reportSearch) {
                $q->where('title', 'like', "%{$reportSearch}%")
                  ->orWhere('description', 'like', "%{$reportSearch}%")
                  ->orWhereHas('employee', function($eq) use ($reportSearch) {
                      $eq->where('full_name', 'like', "%{$reportSearch}%");
                  });
            });
        }
        if ($reportStatus) {
            $reportQuery->where('status', $reportStatus);
        }
        $reportQuery->orderBy('created_at', $reportSort === 'oldest' ? 'asc' : 'desc');
        $expenseReports = $reportQuery->get();

        // Load all approved travel requests and open cash advances to show all options
        $myApprovedTravelRequests = TravelRequest::where('status', 'approved')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();
        $myOpenCashAdvances = CashAdvance::whereIn('status', ['approved', 'disbursed'])
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('modules.hrms.travel-expense.index', compact(
            'employee',
            'employees',
            'categories',
            'designations',
            'travelRequests',
            'cashAdvances',
            'expenseReports',
            'myApprovedTravelRequests',
            'myOpenCashAdvances',
            'isAdmin',
            'travelSearch',
            'travelStatus',
            'travelSort',
            'advanceSearch',
            'advanceStatus',
            'advanceSort',
            'reportSearch',
            'reportStatus',
            'reportSort'
        ));
    }

    // ── TRAVEL REQUESTS ───────────────────────────────────────────────────────

    public function storeTravelRequest(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'employee_id'      => 'required|exists:employees,id',
            'purpose'          => 'required|string|max:255',
            'destination'      => 'required|string|max:255',
            'start_date'       => 'required|date',
            'end_date'         => 'required|date|after_or_equal:start_date',
            'estimated_budget' => 'required|numeric|min:0',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = 'pending';

        TravelRequest::create($validated);

        return redirect()->route('hrms.travel-expense.index', ['tab' => 'travel'])
            ->with('success', 'Travel request submitted successfully.');
    }

    public function approveTravelRequest(TravelRequest $travelRequest): RedirectResponse
    {
        $travelRequest->update(['status' => 'approved']);
        return redirect()->back()->with('success', 'Travel request approved.');
    }

    public function rejectTravelRequest(TravelRequest $travelRequest): RedirectResponse
    {
        $travelRequest->update(['status' => 'rejected']);
        return redirect()->back()->with('success', 'Travel request rejected.');
    }

    // ── CASH ADVANCES ─────────────────────────────────────────────────────────

    public function storeCashAdvance(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'travel_request_id' => 'nullable|exists:travel_requests,id',
            'amount'            => 'required|numeric|min:1',
            'purpose'           => 'required|string|max:255',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = 'pending';

        CashAdvance::create($validated);

        return redirect()->route('hrms.travel-expense.index', ['tab' => 'advance'])
            ->with('success', 'Cash advance request submitted.');
    }

    public function approveCashAdvance(CashAdvance $cashAdvance): RedirectResponse
    {
        // Approve changes state to approved/disbursed
        $cashAdvance->update(['status' => 'disbursed']);
        return redirect()->back()->with('success', 'Cash advance approved and disbursed.');
    }

    public function rejectCashAdvance(CashAdvance $cashAdvance): RedirectResponse
    {
        $cashAdvance->update(['status' => 'rejected']);
        return redirect()->back()->with('success', 'Cash advance request rejected.');
    }

    // ── EXPENSE REPORTS ───────────────────────────────────────────────────────

    public function storeExpenseReport(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'travel_request_id' => 'nullable|exists:travel_requests,id',
            'cash_advance_id'   => 'nullable|exists:cash_advances,id',
            'title'             => 'required|string|max:255',
            'claims'            => 'required|array|min:1',
            'claims.*.category_id' => 'required|exists:expense_categories,id',
            'claims.*.date'        => 'required|date',
            'claims.*.amount'      => 'required|numeric|min:0.01',
            'claims.*.tax'         => 'nullable|numeric|min:0',
            'claims.*.merchant'    => 'nullable|string|max:255',
            'claims.*.desc'        => 'nullable|string|max:1000',
            'claims.*.receipt'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        // Resolve active policy for the employee and enforce limits/receipt requirements
        $employee = Employee::find($validated['employee_id']);
        if ($employee) {
            $policy = ExpensePolicy::where('tenant_id', $tenantId)
                ->where('status', true)
                ->where(function($q) use ($employee) {
                    $q->where('designation_id', $employee->designation_id)
                      ->orWhere('department_id', $employee->department_id)
                      ->orWhere('company_id', $employee->company_id)
                      ->orWhere(function($sub) {
                          $sub->whereNull('designation_id')
                              ->whereNull('department_id')
                              ->whereNull('company_id');
                      });
                })
                ->orderByRaw('CASE 
                    WHEN designation_id IS NOT NULL THEN 1 
                    WHEN department_id IS NOT NULL THEN 2 
                    WHEN company_id IS NOT NULL THEN 3 
                    ELSE 4 
                END')
                ->first();

            if ($policy) {
                $errorMessages = [];
                foreach ($validated['claims'] as $index => $c) {
                    $rule = $policy->rules()->where('expense_category_id', $c['category_id'])->first();
                    if ($rule) {
                        // 1. Max Limit per Claim Check
                        if ($rule->max_limit_per_claim && floatval($c['amount']) > floatval($rule->max_limit_per_claim)) {
                            $errorMessages["claims.{$index}.amount"] = "This claim amount exceeds the policy limit of ₹" . number_format($rule->max_limit_per_claim, 2) . " for category " . $rule->category->name . ".";
                        }
                        
                        // 2. Receipt Requirement Check
                        $needsReceipt = false;
                        if ($rule->receipt_required) {
                            $needsReceipt = true;
                        } elseif ($rule->receipt_required_threshold && floatval($c['amount']) > floatval($rule->receipt_required_threshold)) {
                            $needsReceipt = true;
                        }
                        
                        $fileKey = "claims.{$index}.receipt";
                        if ($needsReceipt && !$request->hasFile($fileKey)) {
                            $errorMessages[$fileKey] = "A receipt attachment is required for " . $rule->category->name . " claims above ₹" . number_format($rule->receipt_required_threshold ?: 0, 2) . ".";
                        }
                    }
                }
                
                if (!empty($errorMessages)) {
                    return redirect()->back()->withInput()->withErrors($errorMessages);
                }
            }
        }

        DB::transaction(function () use ($validated, $request, $tenantId) {
            // 1. Calculate totals
            $totalAmount = 0.00;
            foreach ($validated['claims'] as $c) {
                $totalAmount += floatval($c['amount']);
            }

            // Calculate advance adjustment
            $advanceAdjusted = 0.00;
            $advance = null;
            if (!empty($validated['cash_advance_id'])) {
                $advance = CashAdvance::find($validated['cash_advance_id']);
                if ($advance) {
                    $advanceAdjusted = min(floatval($advance->amount), $totalAmount);
                }
            }

            $netReimbursement = max($totalAmount - $advanceAdjusted, 0.00);

            // 2. Create report record
            $report = ExpenseReport::create([
                'tenant_id'         => $tenantId,
                'employee_id'       => $validated['employee_id'],
                'travel_request_id' => $validated['travel_request_id'] ?: null,
                'title'             => $validated['title'],
                'total_amount'      => $totalAmount,
                'advance_adjusted'  => $advanceAdjusted,
                'net_reimbursement' => $netReimbursement,
                'status'            => 'draft',
            ]);

            // 3. Link cash advance to this report if applicable
            if ($advance) {
                $advance->update(['expense_report_id' => $report->id]);
            }

            // 4. Create individual claim lines
            foreach ($validated['claims'] as $index => $c) {
                $receiptPath = null;
                $fileKey = "claims.{$index}.receipt";
                if ($request->hasFile($fileKey)) {
                    $receiptPath = $request->file($fileKey)->store('expense_receipts', 'public');
                }

                ExpenseClaim::create([
                    'expense_report_id'   => $report->id,
                    'expense_category_id' => $c['category_id'],
                    'expense_date'        => $c['date'],
                    'amount'              => $c['amount'],
                    'tax_amount'          => $c['tax'] ?? 0.00,
                    'merchant'            => $c['merchant'] ?? null,
                    'description'         => $c['desc'] ?? null,
                    'receipt_path'        => $receiptPath,
                ]);
            }
        });

        return redirect()->route('hrms.travel-expense.index', ['tab' => 'report'])
            ->with('success', 'Expense report saved to drafts.');
    }

    public function submitExpenseReport(ExpenseReport $expenseReport): RedirectResponse
    {
        $expenseReport->update(['status' => 'submitted']);
        return redirect()->back()->with('success', 'Expense report submitted for approval.');
    }

    public function approveExpenseReport(ExpenseReport $expenseReport): RedirectResponse
    {
        $expenseReport->update(['status' => 'approved']);
        return redirect()->back()->with('success', 'Expense report approved.');
    }

    public function rejectExpenseReport(ExpenseReport $expenseReport): RedirectResponse
    {
        $expenseReport->update(['status' => 'rejected']);
        return redirect()->back()->with('success', 'Expense report rejected.');
    }

    public function payExpenseReport(ExpenseReport $expenseReport): RedirectResponse
    {
        DB::transaction(function () use ($expenseReport) {
            $expenseReport->update(['status' => 'paid']);

            // If an advance was associated, mark it as settled
            if ($expenseReport->cashAdvance) {
                $expenseReport->cashAdvance->update(['status' => 'settled']);
            }
        });

        return redirect()->back()->with('success', 'Expense report marked as paid and advance settled.');
    }

    // ── EXPENSE POLICIES (MANAGED INSIDE PENALIZATION POLICY) ──────────────────

    public function saveExpensePolicy(Request $request): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'expense_category_id'        => 'required|exists:expense_categories,id',
            'name'                       => 'required|string|max:255',
            'company_id'                 => 'nullable|exists:companies,id',
            'business_unit_id'           => 'nullable|exists:business_units,id',
            'branch_id'                  => 'nullable|exists:branches,id',
            'designation_id'             => 'nullable|exists:designations,id',
            'max_limit_per_claim'        => 'nullable|numeric|min:0',
            'max_monthly_limit'          => 'nullable|numeric|min:0',
            'receipt_required_threshold' => 'nullable|numeric|min:0',
            'status'                     => 'nullable|boolean',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status']    = true; // Active by default; status comes from the top Status dropdown

        ExpensePolicy::updateOrCreate(
            [
                'tenant_id'           => $tenantId,
                'expense_category_id' => $validated['expense_category_id'],
                'designation_id'      => $validated['designation_id'] ?: null,
                'company_id'          => $validated['company_id'] ?: null,
                'business_unit_id'    => $validated['business_unit_id'] ?: null,
                'branch_id'           => $validated['branch_id'] ?: null,
            ],
            $validated
        );

        return redirect()->route('hrms.penalization-policy.index', ['policy_type' => 'expense_rules'])
            ->with('success', 'Expense policy configuration saved successfully.');
    }

    public function deleteExpensePolicy(ExpensePolicy $expensePolicy): RedirectResponse
    {
        $expensePolicy->delete();
        return redirect()->route('hrms.penalization-policy.index', ['tab' => 'expense_rules'])
            ->with('success', 'Expense policy deleted successfully.');
    }
}
