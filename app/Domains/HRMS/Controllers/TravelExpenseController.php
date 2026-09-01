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

        // Resolve company currency symbol
        $company = \App\Domains\HRMS\Models\Company::first();
        $currencyCode = $company?->currency ?? 'USD';
        $currencySymbol = self::currencySymbol($currencyCode);

        // 2. Fetch lists for dropdowns
        $employees = Employee::where('status', true)->orderBy('full_name')->get();
        $categories = ExpenseCategory::where('tenant_id', $tenantId)->where('status', true)->orderBy('name')->get();
        $designations = Designation::where('status', true)->orderBy('name')->get();

        // 3. Fetch operational lists (Travel Requests, Advances, Reports)
        // If user is Admin, show all records; otherwise, show only employee's records
        $isAdmin = ($user->role ?? '') === 'admin';

        // 3. Query lists with sorting, searching, filtering, and tab-safe pagination
        $activeTab = $request->input('tab', 'travel');
        $travelPageName = ($activeTab === 'travel') ? 'page' : 'travel_page';
        $advancePageName = ($activeTab === 'advance') ? 'page' : 'advance_page';
        $reportPageName = ($activeTab === 'report') ? 'page' : 'report_page';

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
        $travelRequests = $travelQuery->paginate(10, ['*'], $travelPageName)->withQueryString();

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
        $cashAdvances = $advanceQuery->paginate(10, ['*'], $advancePageName)->withQueryString();

        $reportSearch = $request->input('report_search');
        $reportStatus = $request->input('report_status');
        $reportSort = $request->input('report_sort', 'newest');

        $reportQuery = ExpenseReport::where('tenant_id', $tenantId)->with(['employee', 'claims.category']);
        if ($reportSearch) {
            $reportQuery->where(function($q) use ($reportSearch) {
                $q->where('title', 'like', "%{$reportSearch}%")
                  ->orWhereHas('employee', function($eq) use ($reportSearch) {
                      $eq->where('full_name', 'like', "%{$reportSearch}%");
                  });
            });
        }
        if ($reportStatus) {
            $reportQuery->where('status', $reportStatus);
        }
        $reportQuery->orderBy('created_at', $reportSort === 'oldest' ? 'asc' : 'desc');
        $expenseReports = $reportQuery->paginate(10, ['*'], $reportPageName)->withQueryString();

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
            'reportSort',
            'currencySymbol',
            'currencyCode'
        ));
    }

    // ── CURRENCY HELPER ───────────────────────────────────────────────────────

    /**
     * Returns the display symbol for a given ISO 4217 currency code.
     * Falls back to the code itself if not in the map.
     */
    public static function currencySymbol(string $code): string
    {
        $map = [
            'USD' => '$',   'EUR' => '€',   'GBP' => '£',   'INR' => '₹',
            'JPY' => '¥',   'CNY' => '¥',   'AUD' => 'A$',  'CAD' => 'CA$',
            'CHF' => 'Fr',  'SGD' => 'S$',  'AED' => 'د.إ', 'SAR' => '﷼',
            'MYR' => 'RM',  'IDR' => 'Rp',  'THB' => '฿',   'PHP' => '₱',
            'BDT' => '৳',   'PKR' => '₨',   'LKR' => '₨',   'NPR' => '₨',
            'BRL' => 'R$',  'MXN' => '$',   'ZAR' => 'R',   'NGN' => '₦',
            'KES' => 'KSh', 'GHS' => '₵',   'EGP' => '£',   'QAR' => '﷼',
            'KWD' => 'KD',  'BHD' => '.د.ب','OMR' => '﷼',   'HKD' => 'HK$',
            'NZD' => 'NZ$', 'SEK' => 'kr',  'NOK' => 'kr',  'DKK' => 'kr',
        ];

        return $map[strtoupper($code)] ?? strtoupper($code);
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
            'request_advance'  => 'nullable|boolean',
            'advance_amount'   => 'nullable|required_if:request_advance,1|numeric|min:1',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = 'pending';

        DB::transaction(function () use ($validated, $request) {
            $travelRequest = TravelRequest::create([
                'tenant_id'        => $validated['tenant_id'],
                'employee_id'      => $validated['employee_id'],
                'purpose'          => $validated['purpose'],
                'destination'      => $validated['destination'],
                'start_date'       => $validated['start_date'],
                'end_date'         => $validated['end_date'],
                'estimated_budget' => $validated['estimated_budget'],
                'status'           => $validated['status'],
            ]);

            if ($request->boolean('request_advance')) {
                CashAdvance::create([
                    'tenant_id'         => $validated['tenant_id'],
                    'employee_id'       => $validated['employee_id'],
                    'travel_request_id' => $travelRequest->id,
                    'amount'            => $validated['advance_amount'],
                    'purpose'           => $validated['purpose'],
                    'status'            => 'pending',
                ]);
            }
        });

        $message = 'Travel request submitted successfully.';
        if ($request->boolean('request_advance')) {
            $message .= ' Linked cash advance request submitted.';
        }

        return redirect()->route('hrms.travel-expense.index', ['tab' => 'travel'])
            ->with('success', $message);
    }

    public function approveTravelRequest(Request $request, TravelRequest $travelRequest): RedirectResponse
    {
        $approvedBudget = $request->input('approved_budget', $travelRequest->estimated_budget);
        
        $travelRequest->update([
            'status' => 'approved',
            'approved_budget' => $approvedBudget
        ]);

        return redirect()->back()->with('success', 'Travel request approved with budget: $' . number_format($approvedBudget, 2));
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

    public function approveCashAdvance(Request $request, CashAdvance $cashAdvance): RedirectResponse
    {
        $approvedAmount = $request->input('approved_amount', $cashAdvance->amount);
        
        $cashAdvance->update([
            'status' => 'approved',
            'approved_amount' => $approvedAmount
        ]);

        return redirect()->back()->with('success', 'Cash advance request approved with amount: $' . number_format($approvedAmount, 2));
    }

    public function disburseCashAdvance(CashAdvance $cashAdvance): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $cashAdvance->update(['status' => 'disbursed']);

        $amount = floatval($cashAdvance->approved_amount ?? $cashAdvance->amount);
        if ($amount > 0) {
            $advancesAccount = $this->getOrCreateAccount($tenantId, '1400', 'Loans & Advances', 'asset', 'debit', 'loans_advances');
            $bankAccount = $this->getOrCreateAccount($tenantId, '1020', 'Bank Account', 'asset', 'debit', 'current_asset');

            $lines = [
                [
                    'chart_of_account_id' => $advancesAccount->id,
                    'debit' => $amount,
                    'credit' => 0.00,
                    'description' => "Disbursement of Advance: " . $cashAdvance->purpose
                ],
                [
                    'chart_of_account_id' => $bankAccount->id,
                    'debit' => 0.00,
                    'credit' => $amount,
                    'description' => "Disbursement of Advance: " . $cashAdvance->purpose
                ]
            ];

            try {
                $journalService = app(\App\Domains\Accounting\Services\JournalService::class);
                $journalService->post($lines, [
                    'tenant_id' => $tenantId,
                    'journal_date' => now(),
                    'source' => 'expense',
                    'reference_type' => 'CashAdvance',
                    'reference_id' => $cashAdvance->id,
                    'memo' => "Disbursed Cash Advance: " . $cashAdvance->purpose,
                    'posted_by' => auth()->id(),
                ]);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Disbursement Journal Posting Failed: " . $e->getMessage());
            }
        }

        return redirect()->back()->with('success', 'Cash advance disbursed successfully.');
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
                    $advanceAmountVal = floatval($advance->approved_amount ?? $advance->amount);
                    $advanceAdjusted = min($advanceAmountVal, $totalAmount);
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

    public function updateExpenseReport(Request $request, ExpenseReport $expenseReport): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        // Enforce update only in draft state
        if ($expenseReport->status !== 'draft') {
            return redirect()->back()->with('error', 'Only draft expense reports can be edited.');
        }

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
            'claims.*.existing_receipt' => 'nullable|string',
        ]);

        // Enforce policies
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
                        if ($rule->max_limit_per_claim && floatval($c['amount']) > floatval($rule->max_limit_per_claim)) {
                            $errorMessages["claims.{$index}.amount"] = "This claim amount exceeds the policy limit of ₹" . number_format($rule->max_limit_per_claim, 2) . " for category " . $rule->category->name . ".";
                        }
                        
                        $needsReceipt = false;
                        if ($rule->receipt_required) {
                            $needsReceipt = true;
                        } elseif ($rule->receipt_required_threshold && floatval($c['amount']) > floatval($rule->receipt_required_threshold)) {
                            $needsReceipt = true;
                        }
                        
                        $fileKey = "claims.{$index}.receipt";
                        // If needs receipt and neither new file uploaded nor existing receipt path present
                        if ($needsReceipt && !$request->hasFile($fileKey) && empty($c['existing_receipt'])) {
                            $errorMessages[$fileKey] = "A receipt attachment is required for " . $rule->category->name . " claims.";
                        }
                    }
                }
                
                if (!empty($errorMessages)) {
                    return redirect()->back()->withInput()->withErrors($errorMessages);
                }
            }
        }

        DB::transaction(function () use ($validated, $request, $tenantId, $expenseReport) {
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
                    $advanceAmountVal = floatval($advance->approved_amount ?? $advance->amount);
                    $advanceAdjusted = min($advanceAmountVal, $totalAmount);
                }
            }

            $netReimbursement = max($totalAmount - $advanceAdjusted, 0.00);

            // Update main report
            $expenseReport->update([
                'travel_request_id' => $validated['travel_request_id'] ?: null,
                'title'             => $validated['title'],
                'total_amount'      => $totalAmount,
                'advance_adjusted'  => $advanceAdjusted,
                'net_reimbursement' => $netReimbursement,
            ]);

            // Unlink any previously linked Cash Advance
            CashAdvance::where('expense_report_id', $expenseReport->id)->update(['expense_report_id' => null]);

            // Link new Cash Advance if applicable
            if ($advance) {
                $advance->update(['expense_report_id' => $expenseReport->id]);
            }

            // Delete old claim lines
            $expenseReport->claims()->delete();

            // Create new claim lines
            foreach ($validated['claims'] as $index => $c) {
                $receiptPath = $c['existing_receipt'] ?? null;
                $fileKey = "claims.{$index}.receipt";
                if ($request->hasFile($fileKey)) {
                    $receiptPath = $request->file($fileKey)->store('expense_receipts', 'public');
                }

                ExpenseClaim::create([
                    'expense_report_id'   => $expenseReport->id,
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
            ->with('success', 'Expense report updated successfully.');
    }

    public function submitExpenseReport(ExpenseReport $expenseReport): RedirectResponse
    {
        $expenseReport->update(['status' => 'submitted']);
        return redirect()->back()->with('success', 'Expense report submitted for approval.');
    }

    public function approveExpenseReport(Request $request, ExpenseReport $expenseReport): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $approvedAmount = $request->input('approved_amount', $expenseReport->total_amount);
        $approvedAmount = floatval($approvedAmount);

        $advance = $expenseReport->cashAdvance;
        $totalAdvance = $advance ? floatval($advance->approved_amount ?? $advance->amount) : 0.00;

        // Capped offset value inside accounting
        $adjusted = min($totalAdvance, $approvedAmount);
        // Net reimbursement payout due to employee (if expense exceeds advance)
        $approvedNet = max($approvedAmount - $totalAdvance, 0.00);

        $payoutChannel = $request->input('payout_channel', 'accounting');

        $expenseReport->update([
            'status' => 'approved',
            'approved_amount' => $approvedAmount,
            'approved_net_reimbursement' => $approvedNet,
            'advance_adjusted' => $adjusted,
            'payout_channel' => $payoutChannel,
        ]);

        if ($payoutChannel === 'accounting') {
            // Do not post to accounting on approval. Accounting entry is posted only on actual payout.
        } else {
            // Payout channel is Payroll:
            // 1. Post the spent offset to Accounting (DR Expense 5900, CR Advances 1400)
            $lines = [];
            $spentAmount = min($adjusted, $approvedAmount);
            if ($spentAmount > 0) {
                $expenseAccount = $this->getOrCreateAccount($tenantId, '5900', 'Other Expense', 'expense', 'debit', 'operating_expense');
                $advancesAccount = $this->getOrCreateAccount($tenantId, '1400', 'Loans & Advances', 'asset', 'debit', 'loans_advances');
                $lines[] = [
                    'chart_of_account_id' => $expenseAccount->id,
                    'debit' => $spentAmount,
                    'credit' => 0.00,
                    'description' => "Expense Claim (Payroll offset): " . $expenseReport->title
                ];
                $lines[] = [
                    'chart_of_account_id' => $advancesAccount->id,
                    'debit' => 0.00,
                    'credit' => $spentAmount,
                    'description' => "Advance offset for Claim: " . $expenseReport->title
                ];

                try {
                    $journalService = app(\App\Domains\Accounting\Services\JournalService::class);
                    $journalService->post($lines, [
                        'tenant_id' => $tenantId,
                        'journal_date' => now(),
                        'source' => 'expense',
                        'reference_type' => 'ExpenseReport',
                        'reference_id' => $expenseReport->id,
                        'memo' => "Approved Expense Claim (Payroll offset): " . $expenseReport->title,
                        'posted_by' => auth()->id(),
                    ]);
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Payroll Claim Journal Posting Failed: " . $e->getMessage());
                }
            }

            // 2. Insert adhoc component entry into payroll
            $employee = $expenseReport->employee;
            if ($employee) {
                $companyId = $employee->company_id ?? (\App\Domains\HRMS\Models\Company::first()?->id ?? 1);
                $payGroupId = $employee->pay_group_id;
                $currentMonth = now()->format('Y-m');

                if ($approvedAmount < $totalAdvance) {
                    // Surplus refund deduction
                    $surplus = $totalAdvance - $approvedAmount;
                    $comp = $this->getOrCreateRecoveryComponent($companyId, $payGroupId);
                    if ($comp) {
                        // Automatically link component to the employee's salary structure if not present
                        if ($employee->salary_structure_id) {
                            \App\Domains\HRMS\Models\SalaryStructureItem::firstOrCreate([
                                'salary_structure_id' => $employee->salary_structure_id,
                                'salary_component_id' => $comp->id,
                            ], [
                                'calculation_type' => 'flat',
                                'value' => 0.00,
                                'sort_order' => 99,
                            ]);
                        }

                        \App\Domains\HRMS\Models\EmployeeAdhocComponent::create([
                            'employee_id' => $employee->id,
                            'salary_component_id' => $comp->id,
                            'amount' => $surplus,
                            'payroll_month' => $currentMonth,
                            'status' => 'pending',
                            'remarks' => "Deduction for T&E Advance Surplus - Claim: " . $expenseReport->title
                        ]);
                    }
                } elseif ($approvedAmount > $totalAdvance) {
                    // Net reimbursement earning
                    $reimbursement = $approvedAmount - $totalAdvance;
                    $comp = $this->getOrCreateReimbursementComponent($companyId, $payGroupId);
                    if ($comp) {
                        // Automatically link component to the employee's salary structure if not present
                        if ($employee->salary_structure_id) {
                            \App\Domains\HRMS\Models\SalaryStructureItem::firstOrCreate([
                                'salary_structure_id' => $employee->salary_structure_id,
                                'salary_component_id' => $comp->id,
                            ], [
                                'calculation_type' => 'flat',
                                'value' => 0.00,
                                'sort_order' => 99,
                            ]);
                        }

                        \App\Domains\HRMS\Models\EmployeeAdhocComponent::create([
                            'employee_id' => $employee->id,
                            'salary_component_id' => $comp->id,
                            'amount' => $reimbursement,
                            'payroll_month' => $currentMonth,
                            'status' => 'pending',
                            'remarks' => "Reimbursement for Travel Expense Claim: " . $expenseReport->title
                        ]);
                    }
                }
            }
        }

        return redirect()->back()->with('success', 'Expense report approved with approved budget: $' . number_format($approvedAmount, 2));
    }

    public function rejectExpenseReport(ExpenseReport $expenseReport): RedirectResponse
    {
        $expenseReport->update(['status' => 'rejected']);
        return redirect()->back()->with('success', 'Expense report rejected.');
    }

    public function payExpenseReport(ExpenseReport $expenseReport): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        DB::transaction(function () use ($expenseReport, $tenantId) {
            $expenseReport->update(['status' => 'paid']);

            $advance = $expenseReport->cashAdvance;
            $isAccountingChannel = ($expenseReport->payout_channel ?? 'accounting') === 'accounting';

            if ($isAccountingChannel) {
                $totalAdvance = $advance ? floatval($advance->approved_amount ?? $advance->amount) : 0.00;
                $approvedAmount = floatval($expenseReport->approved_amount ?? $expenseReport->total_amount);
                $surplus = max($totalAdvance - $approvedAmount, 0.00);
                $approvedNet = max($approvedAmount - $totalAdvance, 0.00);

                $lines = [];

                // 1. Debit Other Expense (Code 5900)
                if ($approvedAmount > 0) {
                    $expenseAccount = $this->getOrCreateAccount($tenantId, '5900', 'Other Expense', 'expense', 'debit', 'operating_expense');
                    $lines[] = [
                        'chart_of_account_id' => $expenseAccount->id,
                        'debit' => $approvedAmount,
                        'credit' => 0.00,
                        'description' => "Expense Claim: " . $expenseReport->title
                    ];
                }

                // 2. Credit Advances (Code 1400)
                if ($totalAdvance > 0) {
                    $advancesAccount = $this->getOrCreateAccount($tenantId, '1400', 'Loans & Advances', 'asset', 'debit', 'loans_advances');
                    $lines[] = [
                        'chart_of_account_id' => $advancesAccount->id,
                        'debit' => 0.00,
                        'credit' => $totalAdvance,
                        'description' => "Clear Advance for Claim: " . $expenseReport->title
                    ];
                }

                // 3. Bank Account transaction (Code 1020)
                if ($surplus > 0 || $approvedNet > 0) {
                    $bankAccount = $this->getOrCreateAccount($tenantId, '1020', 'Bank Account', 'asset', 'debit', 'current_asset');
                    if ($surplus > 0) {
                        // Recovery (Debit Bank)
                        $lines[] = [
                            'chart_of_account_id' => $bankAccount->id,
                            'debit' => $surplus,
                            'credit' => 0.00,
                            'description' => "Advance surplus recovered for Claim: " . $expenseReport->title
                        ];
                    } elseif ($approvedNet > 0) {
                        // Payout (Credit Bank)
                        $lines[] = [
                            'chart_of_account_id' => $bankAccount->id,
                            'debit' => 0.00,
                            'credit' => $approvedNet,
                            'description' => "Payout for Claim: " . $expenseReport->title
                        ];
                    }
                }

                if (count($lines) > 0) {
                    try {
                        $journalService = app(\App\Domains\Accounting\Services\JournalService::class);
                        $journalService->post($lines, [
                            'tenant_id' => $tenantId,
                            'journal_date' => now(),
                            'source' => 'expense',
                            'reference_type' => 'ExpenseReport',
                            'reference_id' => $expenseReport->id,
                            'memo' => "Paid Expense Claim: " . $expenseReport->title,
                            'posted_by' => auth()->id(),
                        ]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Payout Journal Posting Failed: " . $e->getMessage());
                    }
                }
            }

            // If an advance was associated, mark it as settled
            if ($advance) {
                $advance->update(['status' => 'settled']);
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

    public function getEmployeePolicy(Employee $employee): \Illuminate\Http\JsonResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        
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

        $rules = [];
        if ($policy) {
            foreach ($policy->rules as $rule) {
                $rules[$rule->expense_category_id] = [
                    'category_name'              => $rule->category->name,
                    'max_limit_per_claim'        => $rule->max_limit_per_claim ? floatval($rule->max_limit_per_claim) : null,
                    'receipt_required'           => (bool)$rule->receipt_required,
                    'receipt_required_threshold' => $rule->receipt_required_threshold ? floatval($rule->receipt_required_threshold) : null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'policy_name' => $policy ? $policy->name : null,
            'rules' => $rules
        ]);
    }

    /**
     * Get or create a chart of account record for integration.
     * Prevents system crashes if seeded defaults are missing.
     */
    private function getOrCreateAccount(int $tenantId, string $code, string $name, string $type, string $normalBalance, ?string $subtype = null)
    {
        // Try to fetch by exact code and tenant first (pure query, NO inserts)
        $account = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();

        if ($account) {
            return $account;
        }

        $groupAccountIds = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->whereNotNull('parent_id')
            ->pluck('parent_id')
            ->unique()
            ->toArray();

        // If not found, try to find any account of the same type for this tenant (excluding headers)
        $account = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->whereNotIn('id', $groupAccountIds)
            ->first();

        if ($account) {
            return $account;
        }

        // Ultimate fallback: return the first account available (excluding headers)
        return \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->whereNotIn('id', $groupAccountIds)
            ->first();
    }

    /**
     * Get or create a travel advance recovery component for Payroll.
     */
    private function getOrCreateRecoveryComponent(int $companyId, ?int $payGroupId)
    {
        return \App\Domains\HRMS\Models\SalaryComponent::updateOrCreate(
            ['code' => 'TE_RECOVERY', 'company_id' => $companyId, 'pay_group_id' => $payGroupId],
            [
                'name' => 'T&E Advance Recovery',
                'type' => 'deduction',
                'calculation_type' => 'flat',
                'default_value' => 0.00,
                'is_adhoc' => true,
                'status' => true,
                'description' => 'Recovery of surplus cash advance from Travel & Expense module'
            ]
        );
    }

    /**
     * Get or create a travel reimbursement component for Payroll.
     */
    private function getOrCreateReimbursementComponent(int $companyId, ?int $payGroupId)
    {
        return \App\Domains\HRMS\Models\SalaryComponent::updateOrCreate(
            ['code' => 'TE_REIMB', 'company_id' => $companyId, 'pay_group_id' => $payGroupId],
            [
                'name' => 'T&E Reimbursement',
                'type' => 'earning',
                'calculation_type' => 'flat',
                'default_value' => 0.00,
                'is_adhoc' => true,
                'status' => true,
                'description' => 'Reimbursement of travel & expense claims'
            ]
        );
    }
}
