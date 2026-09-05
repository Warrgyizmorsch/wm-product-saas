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
use Illuminate\Support\Facades\Auth;

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
        $isAdmin = $user && (
            in_array(strtolower($user->role ?? ''), ['admin', 'hr', 'super-admin', 'manager']) ||
            (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin', 'hr', 'super-admin', 'manager'])) ||
            (method_exists($user, 'hasRole') && $user->hasRole('admin')) ||
            ($employee && ($employee->is_admin ?? false)) ||
            !$employee
        );

        // 3. Query lists with sorting, searching, filtering, and tab-safe pagination
        $activeTab = $request->input('tab', 'travel');
        $travelPageName = ($activeTab === 'travel') ? 'page' : 'travel_page';
        $advancePageName = ($activeTab === 'advance') ? 'page' : 'advance_page';
        $reportPageName = ($activeTab === 'report') ? 'page' : 'report_page';

        $travelSearch = $request->input('travel_search');
        $travelStatus = $request->input('travel_status');
        $travelSort = $request->input('travel_sort', 'newest');

        $travelQuery = TravelRequest::where('tenant_id', $tenantId)->with(['employee', 'expenseReports', 'cashAdvances']);
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

        $reportQuery = ExpenseReport::where('tenant_id', $tenantId)->with(['employee', 'claims.category', 'travelRequest.cashAdvances', 'travelRequest.expenseReports', 'cashAdvance']);
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
            ->with(['expenseReports', 'cashAdvances'])
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

        $advanceMsg = '';
        if ($request->boolean('approve_cash_advance')) {
            $linkedAdvance = $travelRequest->cashAdvances()->where('status', 'pending')->first();
            if ($linkedAdvance) {
                $approvedAdvanceAmt = $request->input('approved_advance_amount', $linkedAdvance->amount);
                $linkedAdvance->update([
                    'status' => 'approved',
                    'approved_amount' => $approvedAdvanceAmt,
                ]);
                $advanceMsg = ' and linked cash advance';
            }
        }

        return redirect()->back()->with('success', "Travel request{$advanceMsg} approved successfully.");
    }

    public function rejectTravelRequest(TravelRequest $travelRequest): RedirectResponse
    {
        $travelRequest->update(['status' => 'rejected']);
        $travelRequest->cashAdvances()->where('status', 'pending')->update(['status' => 'rejected']);
        return redirect()->back()->with('success', 'Travel request and linked cash advance rejected.');
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
            'claims.*.receipts'   => 'nullable|array',
            'claims.*.receipts.*' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
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
                        
                        $fileKeySingle = "claims.{$index}.receipt";
                        $fileKeyArray  = "claims.{$index}.receipts";
                        $hasFile = $request->hasFile($fileKeySingle) || $request->hasFile($fileKeyArray);

                        if ($needsReceipt && !$hasFile) {
                            $errorMessages[$fileKeyArray] = "A receipt attachment is required for " . $rule->category->name . " claims above ₹" . number_format($rule->receipt_required_threshold ?: 0, 2) . ".";
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
            $adjData = $this->calculateAdvanceAdjustment(
                $validated['travel_request_id'] ? intval($validated['travel_request_id']) : null,
                !empty($validated['cash_advance_id']) ? intval($validated['cash_advance_id']) : null,
                null,
                $totalAmount
            );
            $advanceAdjusted = $adjData['advance_adjusted'];
            $netReimbursement = $adjData['net_reimbursement'];

            $advance = !empty($validated['cash_advance_id']) ? CashAdvance::find($validated['cash_advance_id']) : null;

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
                $storedPaths = [];
                if ($request->hasFile("claims.{$index}.receipt")) {
                    $storedPaths[] = $this->storeAndCompressReceipt($request->file("claims.{$index}.receipt"));
                }
                if ($request->hasFile("claims.{$index}.receipts")) {
                    foreach ($request->file("claims.{$index}.receipts") as $uploadedFile) {
                        if ($uploadedFile && $uploadedFile->isValid()) {
                            $storedPaths[] = $this->storeAndCompressReceipt($uploadedFile);
                        }
                    }
                }
                $storedPaths = array_values(array_unique($storedPaths));
                $receiptPath = null;
                if (count($storedPaths) === 1) {
                    $receiptPath = $storedPaths[0];
                } elseif (count($storedPaths) > 1) {
                    $receiptPath = json_encode($storedPaths);
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

        // Enforce update only in editable states (draft, partially_approved, rejected)
        if (!in_array($expenseReport->status, ['draft', 'partially_approved', 'rejected'])) {
            return redirect()->back()->with('error', 'Only draft, partially approved, or rejected expense reports can be edited.');
        }

        $validated = $request->validate([
            'employee_id'              => 'required|exists:employees,id',
            'travel_request_id'        => 'nullable|exists:travel_requests,id',
            'cash_advance_id'          => 'nullable|exists:cash_advances,id',
            'title'                    => 'required|string|max:255',
            'claims'                   => 'required|array|min:1',
            'claims.*.category_id'     => 'required|exists:expense_categories,id',
            'claims.*.date'            => 'required|date',
            'claims.*.amount'          => 'required|numeric|min:0.01',
            'claims.*.tax'             => 'nullable|numeric|min:0',
            'claims.*.merchant'        => 'nullable|string|max:255',
            'claims.*.desc'            => 'nullable|string|max:1000',
            'claims.*.receipt'         => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'claims.*.receipts'       => 'nullable|array',
            'claims.*.receipts.*'     => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'claims.*.existing_receipt'  => 'nullable|string',
            'claims.*.existing_receipts' => 'nullable|array',
            'claims.*.id'                => 'nullable',
            'claims.*.status'            => 'nullable|string',
            'claims.*.approved_amount'   => 'nullable',
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
                        
                        $fileKeySingle = "claims.{$index}.receipt";
                        $fileKeyArray  = "claims.{$index}.receipts";
                        $hasFile = $request->hasFile($fileKeySingle) || $request->hasFile($fileKeyArray);
                        $hasExisting = !empty($c['existing_receipt']) || !empty($c['existing_receipts']);

                        if ($needsReceipt && !$hasFile && !$hasExisting) {
                            $errorMessages[$fileKeyArray] = "A receipt attachment is required for " . $rule->category->name . " claims.";
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
            $adjData = $this->calculateAdvanceAdjustment(
                $validated['travel_request_id'] ? intval($validated['travel_request_id']) : null,
                !empty($validated['cash_advance_id']) ? intval($validated['cash_advance_id']) : null,
                $expenseReport->id,
                $totalAmount
            );
            $advanceAdjusted = $adjData['advance_adjusted'];
            $netReimbursement = $adjData['net_reimbursement'];

            $advance = !empty($validated['cash_advance_id']) ? CashAdvance::find($validated['cash_advance_id']) : null;

            // Update main report
            $expenseReport->update([
                'travel_request_id'          => $validated['travel_request_id'] ?: null,
                'title'                      => $validated['title'],
                'total_amount'               => $totalAmount,
                'advance_adjusted'           => $advanceAdjusted,
                'net_reimbursement'          => $netReimbursement,
                'approved_amount'            => null,
                'approved_net_reimbursement' => null,
                'status'                     => 'submitted',
            ]);

            // Unlink any previously linked Cash Advance
            CashAdvance::where('expense_report_id', $expenseReport->id)->update(['expense_report_id' => null]);

            // Link new Cash Advance if applicable
            if ($advance) {
                $advance->update(['expense_report_id' => $expenseReport->id]);
            }

            // Map existing claims by ID before clearing non-paid lines
            $existingClaimsMap = $expenseReport->claims->keyBy('id');
            $expenseReport->claims()->where('status', '!=', 'paid')->delete();

            // Create/resubmit claim lines
            foreach ($validated['claims'] as $index => $c) {
                $storedPaths = [];

                if (!empty($c['existing_receipts']) && is_array($c['existing_receipts'])) {
                    foreach ($c['existing_receipts'] as $ep) {
                        if (!empty($ep)) {
                            $storedPaths[] = $ep;
                        }
                    }
                } elseif (!empty($c['existing_receipt'])) {
                    if (str_starts_with($c['existing_receipt'], '[')) {
                        $dec = json_decode($c['existing_receipt'], true);
                        if (is_array($dec)) {
                            $storedPaths = array_merge($storedPaths, $dec);
                        }
                    } else {
                        $storedPaths[] = $c['existing_receipt'];
                    }
                }

                if ($request->hasFile("claims.{$index}.receipt")) {
                    $storedPaths[] = $this->storeAndCompressReceipt($request->file("claims.{$index}.receipt"));
                }

                if ($request->hasFile("claims.{$index}.receipts")) {
                    foreach ($request->file("claims.{$index}.receipts") as $uploadedFile) {
                        if ($uploadedFile && $uploadedFile->isValid()) {
                            $storedPaths[] = $this->storeAndCompressReceipt($uploadedFile);
                        }
                    }
                }

                $storedPaths = array_values(array_unique($storedPaths));
                $receiptPath = null;
                if (count($storedPaths) === 1) {
                    $receiptPath = $storedPaths[0];
                } elseif (count($storedPaths) > 1) {
                    $receiptPath = json_encode($storedPaths);
                }

                $claimId = $c['id'] ?? null;
                $existingClaim = $claimId ? $existingClaimsMap->get($claimId) : null;

                $claimStatus = 'pending';
                $approvedAmount = null;

                if ($existingClaim && $existingClaim->status === 'approved') {
                    $claimedVal = floatval($c['amount']);
                    $existingVal = floatval($existingClaim->amount);
                    if (abs($claimedVal - $existingVal) < 0.01) {
                        $claimStatus = 'approved';
                        $approvedAmount = floatval($c['approved_amount'] ?? $existingClaim->approved_amount ?? $c['amount']);
                    }
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
                    'status'              => $claimStatus,
                    'approved_amount'     => $approvedAmount,
                ]);
            }
        });

        return redirect()->route('hrms.travel-expense.index', ['tab' => 'report'])
            ->with('success', 'Expense report resubmitted successfully.');
    }

    public function submitExpenseReport(ExpenseReport $expenseReport): RedirectResponse
    {
        $expenseReport->update(['status' => 'submitted']);
        return redirect()->back()->with('success', 'Expense report submitted for approval.');
    }

    public function approveExpenseReport(Request $request, ExpenseReport $expenseReport): RedirectResponse
    {
        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        // Check for itemized claim line decisions
        $itemDecisions = $request->input('items', []);
        $status = 'approved';

        if (!empty($itemDecisions)) {
            $totalApproved = 0.00;
            $approvedCount = 0;
            $rejectedCount = 0;

            foreach ($itemDecisions as $claimId => $itemData) {
                $claim = ExpenseClaim::where('expense_report_id', $expenseReport->id)->find($claimId);
                if ($claim) {
                    $itemDecision = $itemData['decision'] ?? 'approved';
                    $rejectionReason = $itemData['rejection_reason'] ?? null;

                    if ($itemDecision === 'approved') {
                        $itemApprovedVal = isset($itemData['approved_amount']) && $itemData['approved_amount'] !== '' 
                            ? floatval($itemData['approved_amount']) 
                            : floatval($claim->amount);

                        $claim->update([
                            'status'           => 'approved',
                            'approved_amount'  => $itemApprovedVal,
                            'rejection_reason' => null,
                        ]);
                        $totalApproved += $itemApprovedVal;
                        $approvedCount++;
                    } else {
                        $claim->update([
                            'status'           => 'rejected',
                            'approved_amount'  => 0.00,
                            'rejection_reason' => $rejectionReason ?: 'Rejected by administrator.',
                        ]);
                        $rejectedCount++;
                    }
                }
            }

            if ($approvedCount > 0 && $rejectedCount > 0) {
                $status = 'partially_approved';
            } elseif ($approvedCount > 0 && $rejectedCount === 0) {
                $status = 'approved';
            } else {
                $status = 'rejected';
            }

            $approvedAmount = $totalApproved;
        } else {
            $approvedAmount = floatval($request->input('approved_amount', $expenseReport->total_amount));
            $status = 'approved';

            foreach ($expenseReport->claims as $c) {
                $c->update([
                    'status'          => 'approved',
                    'approved_amount' => $c->amount,
                ]);
            }
        }

        $adjData = $this->calculateAdvanceAdjustment(
            $expenseReport->travel_request_id,
            $expenseReport->cash_advance_id ?? $expenseReport->cashAdvance?->id,
            $expenseReport->id,
            $approvedAmount,
            $expenseReport->employee_id
        );
        $adjusted = $adjData['advance_adjusted'];
        $approvedNet = $adjData['net_reimbursement'];

        $payoutChannel = $request->input('payout_channel', 'accounting');

        $expenseReport->update([
            'status'                     => $status,
            'approved_amount'            => $approvedAmount,
            'approved_net_reimbursement' => $approvedNet,
            'advance_adjusted'          => $adjusted,
            'payout_channel'             => $payoutChannel,
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

            // 2. Manage adhoc component entries in payroll based on trip-level surplus/reimbursement
            $employee = $expenseReport->employee;
            if ($employee) {
                $companyId = $employee->company_id ?? (\App\Domains\HRMS\Models\Company::first()?->id ?? 1);
                $payGroupId = $employee->pay_group_id;
                $currentMonth = now()->format('Y-m');

                $recoveryComp = $this->getOrCreateRecoveryComponent($companyId, $payGroupId);

                $trId = $expenseReport->travel_request_id;
                $totalTripAdvance = $adjData['total_advance'];
                $processedRecovery = $adjData['processed_payroll_recovery'];

                // Calculate cumulative approved expenses across all trip reports
                $allApprovedExpenses = $trId 
                    ? ExpenseReport::where('travel_request_id', $trId)->whereIn('status', ['approved', 'partially_approved', 'paid'])->sum(fn($r) => floatval($r->approved_amount ?? $r->total_amount))
                    : $approvedAmount;

                $effectiveTripSurplus = max($totalTripAdvance - $allApprovedExpenses - $processedRecovery, 0.00);

                // Fetch any PENDING recovery adhoc entries for this employee
                $pendingAdhocs = \App\Domains\HRMS\Models\EmployeeAdhocComponent::where('employee_id', $employee->id)
                    ->where('salary_component_id', $recoveryComp->id)
                    ->where('status', 'pending')
                    ->get();

                if ($effectiveTripSurplus <= 0) {
                    // Cash advance is now fully settled by expenses! Remove pending ad-hoc payroll recovery deduction
                    foreach ($pendingAdhocs as $pAdhoc) {
                        $pAdhoc->delete();
                    }
                } else {
                    // Update pending adhoc deduction to the remaining trip surplus amount
                    if ($pendingAdhocs->isNotEmpty()) {
                        $firstPending = $pendingAdhocs->first();
                        $firstPending->update(['amount' => $effectiveTripSurplus]);
                        foreach ($pendingAdhocs->skip(1) as $pAdhoc) {
                            $pAdhoc->delete();
                        }
                    } else {
                        if ($employee->salary_structure_id) {
                            \App\Domains\HRMS\Models\SalaryStructureItem::firstOrCreate([
                                'salary_structure_id' => $employee->salary_structure_id,
                                'salary_component_id' => $recoveryComp->id,
                            ], [
                                'calculation_type' => 'flat',
                                'value' => 0.00,
                                'sort_order' => 99,
                            ]);
                        }

                        \App\Domains\HRMS\Models\EmployeeAdhocComponent::create([
                            'employee_id' => $employee->id,
                            'salary_component_id' => $recoveryComp->id,
                            'amount' => $effectiveTripSurplus,
                            'payroll_month' => $currentMonth,
                            'status' => 'pending',
                            'remarks' => "Deduction for T&E Advance Surplus - Claim: " . $expenseReport->title
                        ]);
                    }
                }

                // If this report produces a net reimbursement, create a pending reimbursement adhoc component
                if ($approvedNet > 0) {
                    $reimbComp = $this->getOrCreateReimbursementComponent($companyId, $payGroupId);
                    if ($reimbComp) {
                        if ($employee->salary_structure_id) {
                            \App\Domains\HRMS\Models\SalaryStructureItem::firstOrCreate([
                                'salary_structure_id' => $employee->salary_structure_id,
                                'salary_component_id' => $reimbComp->id,
                            ], [
                                'calculation_type' => 'flat',
                                'value' => 0.00,
                                'sort_order' => 99,
                            ]);
                        }

                        \App\Domains\HRMS\Models\EmployeeAdhocComponent::create([
                            'employee_id' => $employee->id,
                            'salary_component_id' => $reimbComp->id,
                            'amount' => $approvedNet,
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
            // Find newly approved claims that have not been paid yet
            $approvedClaims = $expenseReport->claims()->where('status', 'approved')->get();
            $unpaidApprovedAmount = $approvedClaims->sum(function($c) {
                return floatval($c->approved_amount ?? $c->amount);
            });

            // If no explicit claim lines are marked 'approved', fall back to report approved amount
            if ($unpaidApprovedAmount <= 0 && in_array($expenseReport->status, ['approved', 'partially_approved'])) {
                $unpaidApprovedAmount = floatval($expenseReport->approved_amount ?? $expenseReport->total_amount);
            }

            // Mark approved claim lines as paid
            $expenseReport->claims()->where('status', 'approved')->update(['status' => 'paid']);

            // Determine if all claim lines are now paid
            $unpaidCount = $expenseReport->claims()->whereIn('status', ['pending', 'approved'])->count();
            if ($unpaidCount === 0) {
                $expenseReport->update(['status' => 'paid']);
            } else {
                $expenseReport->update(['status' => 'partially_approved']);
            }

            $advance = $expenseReport->cashAdvance;
            $isAccountingChannel = ($expenseReport->payout_channel ?? 'accounting') === 'accounting';

            if ($isAccountingChannel && $unpaidApprovedAmount > 0) {
                $adjData = $this->calculateAdvanceAdjustment(
                    $expenseReport->travel_request_id,
                    $expenseReport->cash_advance_id ?? $expenseReport->cashAdvance?->id,
                    $expenseReport->id,
                    $unpaidApprovedAmount
                );
                $remainingAdvance = $adjData['remaining_advance'];
                $offsetAmount = min($remainingAdvance, $unpaidApprovedAmount);
                $approvedNet = max($unpaidApprovedAmount - $remainingAdvance, 0.00);
                $surplus = max($remainingAdvance - $unpaidApprovedAmount, 0.00);

                $expenseReport->update([
                    'advance_adjusted'          => $adjData['advance_adjusted'],
                    'approved_net_reimbursement' => $approvedNet,
                ]);

                $lines = [];

                // 1. Debit Other Expense (Code 5900)
                $expenseAccount = $this->getOrCreateAccount($tenantId, '5900', 'Other Expense', 'expense', 'debit', 'operating_expense');
                $lines[] = [
                    'chart_of_account_id' => $expenseAccount->id,
                    'debit' => $unpaidApprovedAmount,
                    'credit' => 0.00,
                    'description' => "Expense Claim Payout: " . $expenseReport->title
                ];

                // 2. Credit Advances (Code 1400)
                if ($offsetAmount > 0) {
                    $advancesAccount = $this->getOrCreateAccount($tenantId, '1400', 'Loans & Advances', 'asset', 'debit', 'loans_advances');
                    $lines[] = [
                        'chart_of_account_id' => $advancesAccount->id,
                        'debit' => 0.00,
                        'credit' => $offsetAmount,
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
                            'memo' => "Paid Expense Claim Portion: " . $expenseReport->title,
                            'posted_by' => auth()->id(),
                        ]);
                    } catch (\Exception $e) {
                        \Illuminate\Support\Facades\Log::error("Payout Journal Posting Failed: " . $e->getMessage());
                    }
                }
            }

            // Settle cash advance
            if ($advance) {
                $advance->update(['status' => 'settled']);
            }
        });

        return redirect()->back()->with('success', 'Expense payout processed successfully and accounting entries updated.');
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
    /**
     * Store and automatically compress uploaded receipt files (Images/PDFs).
     */
    private function storeAndCompressReceipt($uploadedFile): string
    {
        if (!$uploadedFile || !$uploadedFile->isValid()) {
            return '';
        }

        $extension = strtolower($uploadedFile->getClientOriginalExtension());
        $imageExtensions = ['jpg', 'jpeg', 'png', 'webp'];

        // If not an image or GD library not available, fallback to standard file storage
        if (!in_array($extension, $imageExtensions) || !extension_loaded('gd')) {
            return $uploadedFile->store('expense_receipts', 'public');
        }

        try {
            $filePath = $uploadedFile->getRealPath();
            $image = null;

            switch ($extension) {
                case 'jpg':
                case 'jpeg':
                    if (function_exists('imagecreatefromjpeg')) {
                        $image = @imagecreatefromjpeg($filePath);
                    }
                    break;
                case 'png':
                    if (function_exists('imagecreatefrompng')) {
                        $image = @imagecreatefrompng($filePath);
                    }
                    break;
                case 'webp':
                    if (function_exists('imagecreatefromwebp')) {
                        $image = @imagecreatefromwebp($filePath);
                    }
                    break;
            }

            if (!$image && function_exists('imagecreatefromstring')) {
                $image = @imagecreatefromstring(file_get_contents($filePath));
            }

            if (!$image) {
                return $uploadedFile->store('expense_receipts', 'public');
            }

            // Get dimensions
            $origWidth = imagesx($image);
            $origHeight = imagesy($image);
            $maxDimension = 1920;

            // Scale down if image is larger than 1920px
            if ($origWidth > $maxDimension || $origHeight > $maxDimension) {
                if ($origWidth > $origHeight) {
                    $newWidth = $maxDimension;
                    $newHeight = (int) round(($origHeight / $origWidth) * $maxDimension);
                } else {
                    $newHeight = $maxDimension;
                    $newWidth = (int) round(($origWidth / $origHeight) * $maxDimension);
                }

                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

                // Preserve PNG transparency
                if ($extension === 'png') {
                    imagealphablending($resizedImage, false);
                    imagesavealpha($resizedImage, true);
                }

                imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                imagedestroy($image);
                $image = $resizedImage;
            }

            // Save to public storage
            $fileName = 'receipt_' . uniqid() . '_' . time() . '.' . ($extension === 'png' ? 'png' : 'jpg');
            $storageDir = storage_path('app/public/expense_receipts');
            if (!file_exists($storageDir)) {
                mkdir($storageDir, 0755, true);
            }

            $destinationPath = $storageDir . '/' . $fileName;

            if ($extension === 'png' && function_exists('imagepng')) {
                imagepng($image, $destinationPath, 6);
            } else {
                imagejpeg($image, $destinationPath, 80);
            }

            imagedestroy($image);

            return 'expense_receipts/' . $fileName;

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("Receipt compression fallback: " . $e->getMessage());
            return $uploadedFile->store('expense_receipts', 'public');
        }
    }

    /**
     * Calculate cash advance adjustment and net reimbursement across all reports for a travel trip.
     */
    private function calculateAdvanceAdjustment(?int $travelRequestId, ?int $cashAdvanceId, ?int $expenseReportId, float $amount, ?int $employeeId = null): array
    {
        $totalTripAdvance = 0.00;
        $otherAdjusted = 0.00;
        $processedPayrollRecovery = 0.00;

        if ($travelRequestId) {
            $travelRequest = TravelRequest::with(['cashAdvances', 'expenseReports'])->find($travelRequestId);
            if ($travelRequest) {
                $totalTripAdvance = $travelRequest->cashAdvances
                    ->whereIn('status', ['approved', 'disbursed', 'settled', 'paid'])
                    ->sum(fn($ca) => floatval($ca->approved_amount ?? $ca->amount));

                $otherAdjusted = $travelRequest->expenseReports
                    ->when($expenseReportId, fn($q) => $q->where('id', '!=', $expenseReportId))
                    ->whereIn('status', ['approved', 'partially_approved', 'paid'])
                    ->sum(fn($er) => floatval($er->advance_adjusted ?? 0.00));

                $empId = $employeeId ?? $travelRequest->employee_id;
                if ($empId) {
                    $recoveryCompIds = \App\Domains\HRMS\Models\SalaryComponent::whereIn('code', ['TE_RECOVERY', 'ADV_RECOVERY'])->pluck('id');
                    if ($recoveryCompIds->isNotEmpty()) {
                        $processedPayrollRecovery = \App\Domains\HRMS\Models\EmployeeAdhocComponent::where('employee_id', $empId)
                            ->whereIn('salary_component_id', $recoveryCompIds)
                            ->where('status', 'processed')
                            ->sum('amount');
                    }
                }
            }
        }

        if ($totalTripAdvance <= 0 && $cashAdvanceId) {
            $ca = CashAdvance::find($cashAdvanceId);
            if ($ca) {
                $totalTripAdvance = floatval($ca->approved_amount ?? $ca->amount);
            }
        }

        $effectiveAdvanceAvailable = max($totalTripAdvance - $processedPayrollRecovery, 0.00);
        $remainingAdvance = max($effectiveAdvanceAvailable - $otherAdjusted, 0.00);
        $adjusted = min($remainingAdvance, $amount);
        $netReimbursement = max($amount - $adjusted, 0.00);

        return [
            'total_advance'             => $totalTripAdvance,
            'processed_payroll_recovery' => $processedPayrollRecovery,
            'effective_advance'         => $effectiveAdvanceAvailable,
            'remaining_advance'         => $remainingAdvance,
            'advance_adjusted'          => $adjusted,
            'net_reimbursement'         => $netReimbursement,
        ];
    }
}
