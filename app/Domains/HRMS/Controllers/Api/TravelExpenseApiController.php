<?php

namespace App\Domains\HRMS\Controllers\Api;

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
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class TravelExpenseApiController extends Controller
{
    /**
     * Helper for standardized success JSON response.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Helper for standardized error JSON response.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        return response()->json($response, $statusCode);
    }

    /**
     * Null-safe authorization check.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();
            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth credentials.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access.', 401);
            }
        }
        return null;
    }

    /**
     * GET /api/hrms/travel-expense
     * Dashboard listings of travel requests, cash advances, and reports.
     */
    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $user = auth()->user();

        // 1. Resolve employee context
        $employee = null;
        if ($user && $user->email) {
            $employee = Employee::where('personal_email', $user->email)
                ->orWhere('office_email', $user->email)
                ->first();
        }

        $isAdmin = ($user->role ?? '') === 'admin';

        // 2. Query lists
        $travelQuery = TravelRequest::where('tenant_id', $tenantId)->with('employee');
        $advanceQuery = CashAdvance::where('tenant_id', $tenantId)->with(['employee', 'travelRequest']);
        $reportQuery = ExpenseReport::where('tenant_id', $tenantId)->with(['employee', 'claims.category']);

        if (!$isAdmin && $employee) {
            $travelQuery->where('employee_id', $employee->id);
            $advanceQuery->where('employee_id', $employee->id);
            $reportQuery->where('employee_id', $employee->id);
        }

        // Apply travel search & status
        if ($travelSearch = $request->input('travel_search')) {
            $travelQuery->where(function($q) use ($travelSearch) {
                $q->where('purpose', 'like', "%{$travelSearch}%")
                  ->orWhere('destination', 'like', "%{$travelSearch}%");
            });
        }
        if ($travelStatus = $request->input('travel_status')) {
            $travelQuery->where('status', $travelStatus);
        }

        // Apply advance search & status
        if ($advanceSearch = $request->input('advance_search')) {
            $advanceQuery->where(function($q) use ($advanceSearch) {
                $q->where('purpose', 'like', "%{$advanceSearch}%");
            });
        }
        if ($advanceStatus = $request->input('advance_status')) {
            $advanceQuery->where('status', $advanceStatus);
        }

        // Apply report search & status
        if ($reportSearch = $request->input('report_search')) {
            $reportQuery->where(function($q) use ($reportSearch) {
                $q->where('title', 'like', "%{$reportSearch}%");
            });
        }
        if ($reportStatus = $request->input('report_status')) {
            $reportQuery->where('status', $reportStatus);
        }

        // Resolve company currency symbol
        $company = \App\Domains\HRMS\Models\Company::first();
        $currencyCode = $company?->currency ?? 'USD';
        $currencySymbol = self::currencySymbol($currencyCode);

        // Load all approved travel requests and open cash advances to show all options
        $myApprovedTravelRequests = TravelRequest::where('status', 'approved')
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();
        $myOpenCashAdvances = CashAdvance::whereIn('status', ['approved', 'disbursed'])
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Metadata dropdowns
        $categories = ExpenseCategory::where('tenant_id', $tenantId)->where('status', true)->orderBy('name')->get();
        $designations = Designation::where('status', true)->orderBy('name')->get();
        $employees = Employee::where('status', true)->orderBy('full_name')->get();

        return $this->sendSuccess([
            'employee'                  => $employee,
            'is_admin'                  => $isAdmin,
            'currency_code'             => $currencyCode,
            'currency_symbol'           => $currencySymbol,
            'my_approved_travel_requests'=> $myApprovedTravelRequests,
            'my_open_cash_advances'     => $myOpenCashAdvances,
            'dropdowns'                 => [
                'employees'          => $employees,
                'expense_categories' => $categories,
                'designations'       => $designations,
            ],
            'travel_requests'           => $travelQuery->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 10), ['*'], 'travel_page'),
            'cash_advances'             => $advanceQuery->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 10), ['*'], 'advance_page'),
            'expense_reports'           => $reportQuery->orderBy('created_at', 'desc')->paginate($request->integer('per_page', 10), ['*'], 'report_page'),
        ], 'Travel & Expense dashboard data loaded successfully');
    }

    /**
     * Returns the display symbol for a given ISO 4217 currency code.
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

    /**
     * POST /api/hrms/travel-expense/travel/store
     */
    public function storeTravelRequest(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

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

        $travelRequest = DB::transaction(function () use ($validated, $request) {
            $tr = TravelRequest::create([
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
                    'travel_request_id' => $tr->id,
                    'amount'            => $validated['advance_amount'],
                    'purpose'           => $validated['purpose'],
                    'status'            => 'pending',
                ]);
            }

            return $tr;
        });

        return $this->sendSuccess($travelRequest->load('cashAdvances'), 'Travel request submitted successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/travel/{travelRequest}/approve
     */
    public function approveTravelRequest(Request $request, TravelRequest $travelRequest): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $approvedBudget = $request->input('approved_budget', $travelRequest->estimated_budget);
        
        $travelRequest->update([
            'status' => 'approved',
            'approved_budget' => $approvedBudget
        ]);

        return $this->sendSuccess($travelRequest, 'Travel request approved successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/travel/{travelRequest}/reject
     */
    public function rejectTravelRequest(TravelRequest $travelRequest): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $travelRequest->update(['status' => 'rejected']);
        return $this->sendSuccess($travelRequest, 'Travel request rejected successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/advance/store
     */
    public function storeCashAdvance(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        $validated = $request->validate([
            'employee_id'       => 'required|exists:employees,id',
            'travel_request_id' => 'nullable|exists:travel_requests,id',
            'amount'            => 'required|numeric|min:1',
            'purpose'           => 'required|string|max:255',
        ]);

        $validated['tenant_id'] = $tenantId;
        $validated['status'] = 'pending';

        $cashAdvance = CashAdvance::create($validated);

        return $this->sendSuccess($cashAdvance, 'Cash advance request submitted successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/advance/{cashAdvance}/approve
     */
    public function approveCashAdvance(Request $request, CashAdvance $cashAdvance): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $approvedAmount = $request->input('approved_amount', $cashAdvance->amount);
        
        $cashAdvance->update([
            'status' => 'approved',
            'approved_amount' => $approvedAmount
        ]);

        return $this->sendSuccess($cashAdvance, 'Cash advance request approved successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/advance/{cashAdvance}/disburse
     */
    public function disburseCashAdvance(CashAdvance $cashAdvance): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

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
                Log::error("Disbursement Journal Posting Failed: " . $e->getMessage());
            }
        }

        return $this->sendSuccess($cashAdvance, 'Cash advance disbursed successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/advance/{cashAdvance}/reject
     */
    public function rejectCashAdvance(CashAdvance $cashAdvance): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $cashAdvance->update(['status' => 'rejected']);
        return $this->sendSuccess($cashAdvance, 'Cash advance request rejected successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/report/store
     */
    public function storeExpenseReport(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

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
                        if ($needsReceipt && !$request->hasFile($fileKey)) {
                            $errorMessages[$fileKey] = "A receipt attachment is required for " . $rule->category->name . " claims above ₹" . number_format($rule->receipt_required_threshold ?: 0, 2) . ".";
                        }
                    }
                }
                
                if (!empty($errorMessages)) {
                    return $this->sendError('Expense policy validation failed.', 422, $errorMessages);
                }
            }
        }

        $report = DB::transaction(function () use ($validated, $request, $tenantId) {
            $totalAmount = 0.00;
            foreach ($validated['claims'] as $c) {
                $totalAmount += floatval($c['amount']);
            }

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

            $rep = ExpenseReport::create([
                'tenant_id'         => $tenantId,
                'employee_id'       => $validated['employee_id'],
                'travel_request_id' => $validated['travel_request_id'] ?: null,
                'title'             => $validated['title'],
                'total_amount'      => $totalAmount,
                'advance_adjusted'  => $advanceAdjusted,
                'net_reimbursement' => $netReimbursement,
                'status'            => 'draft',
            ]);

            if ($advance) {
                $advance->update(['expense_report_id' => $rep->id]);
            }

            foreach ($validated['claims'] as $index => $c) {
                $receiptPath = null;
                $fileKey = "claims.{$index}.receipt";
                if ($request->hasFile($fileKey)) {
                    $receiptPath = $request->file($fileKey)->store('expense_receipts', 'public');
                }

                ExpenseClaim::create([
                    'expense_report_id'   => $rep->id,
                    'expense_category_id' => $c['category_id'],
                    'expense_date'        => $c['date'],
                    'amount'              => $c['amount'],
                    'tax_amount'          => $c['tax'] ?? 0.00,
                    'merchant'            => $c['merchant'] ?? null,
                    'description'         => $c['desc'] ?? null,
                    'receipt_path'        => $receiptPath,
                ]);
            }

            return $rep;
        });

        return $this->sendSuccess($report->load('claims'), 'Expense report saved to drafts.');
    }

    /**
     * POST /api/hrms/travel-expense/report/{expenseReport}/update
     */
    public function updateExpenseReport(Request $request, ExpenseReport $expenseReport): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();

        if ($expenseReport->status !== 'draft') {
            return $this->sendError('Only draft expense reports can be edited.', 400);
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
                        if ($needsReceipt && !$request->hasFile($fileKey) && empty($c['existing_receipt'])) {
                            $errorMessages[$fileKey] = "A receipt attachment is required for " . $rule->category->name . " claims.";
                        }
                    }
                }
                
                if (!empty($errorMessages)) {
                    return $this->sendError('Expense policy validation failed.', 422, $errorMessages);
                }
            }
        }

        DB::transaction(function () use ($validated, $request, $tenantId, $expenseReport) {
            $totalAmount = 0.00;
            foreach ($validated['claims'] as $c) {
                $totalAmount += floatval($c['amount']);
            }

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

            $expenseReport->update([
                'travel_request_id' => $validated['travel_request_id'] ?: null,
                'title'             => $validated['title'],
                'total_amount'      => $totalAmount,
                'advance_adjusted'  => $advanceAdjusted,
                'net_reimbursement' => $netReimbursement,
            ]);

            CashAdvance::where('expense_report_id', $expenseReport->id)->update(['expense_report_id' => null]);

            if ($advance) {
                $advance->update(['expense_report_id' => $expenseReport->id]);
            }

            $expenseReport->claims()->delete();

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

        return $this->sendSuccess($expenseReport->fresh()->load('claims'), 'Expense report updated successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/report/{expenseReport}/submit
     */
    public function submitExpenseReport(ExpenseReport $expenseReport): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $expenseReport->update(['status' => 'submitted']);
        return $this->sendSuccess($expenseReport, 'Expense report submitted for approval.');
    }

    /**
     * POST /api/hrms/travel-expense/report/{expenseReport}/approve
     */
    public function approveExpenseReport(Request $request, ExpenseReport $expenseReport): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $tenantId = tenant_id() ?? app(\App\Core\Tenant\TenantContext::class)->id();
        $approvedAmount = floatval($request->input('approved_amount', $expenseReport->total_amount));

        $advance = $expenseReport->cashAdvance;
        $totalAdvance = $advance ? floatval($advance->approved_amount ?? $advance->amount) : 0.00;

        $adjusted = min($totalAdvance, $approvedAmount);
        $approvedNet = max($approvedAmount - $totalAdvance, 0.00);

        $payoutChannel = $request->input('payout_channel', 'accounting');

        DB::transaction(function () use ($expenseReport, $tenantId, $approvedAmount, $approvedNet, $adjusted, $payoutChannel, $advance) {
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
                $employee = $expenseReport->employee;
                if ($employee) {
                    $currentMonth = Carbon::now()->format('Y-m');

                    if ($adjusted < $totalAdvance) {
                        $surplus = $totalAdvance - $adjusted;
                        if ($surplus > 0) {
                            $comp = $this->getOrCreateRecoveryComponent($employee->company_id, $employee->pay_group_id);
                            if ($comp && $employee->salary_structure_id) {
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
                                'remarks' => "Surplus Recovery for Travel Advance: " . $expenseReport->title
                            ]);
                        }
                    }

                    $reimbursement = $approvedNet;
                    if ($reimbursement > 0) {
                        $comp = $this->getOrCreateReimbursementComponent($employee->company_id, $employee->pay_group_id);
                        if ($comp && $employee->salary_structure_id) {
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
        });

        return $this->sendSuccess($expenseReport->fresh(), 'Expense report approved successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/report/{expenseReport}/reject
     */
    public function rejectExpenseReport(ExpenseReport $expenseReport): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $expenseReport->update(['status' => 'rejected']);
        return $this->sendSuccess($expenseReport, 'Expense report rejected successfully.');
    }

    /**
     * POST /api/hrms/travel-expense/report/{expenseReport}/pay
     */
    public function payExpenseReport(ExpenseReport $expenseReport): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

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
                        Log::error("Payout Journal Posting Failed: " . $e->getMessage());
                    }
                }
            }

            // If an advance was associated, mark it as settled
            if ($advance) {
                $advance->update(['status' => 'settled']);
            }
        });

        return $this->sendSuccess($expenseReport->fresh(), 'Expense report marked as paid and advance settled successfully.');
    }

    /**
     * GET /api/hrms/travel-expense/employee-policy/{employee}
     */
    public function getEmployeePolicy(Employee $employee): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

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

        return $this->sendSuccess([
            'policy_name' => $policy ? $policy->name : null,
            'rules'       => $rules
        ], 'Employee active expense policy retrieved.');
    }

    /**
     * Get or create a chart of account record for integration.
     */
    private function getOrCreateAccount(int $tenantId, string $code, string $name, string $type, string $normalBalance, ?string $subtype = null)
    {
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

        $account = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', $type)
            ->whereNotIn('id', $groupAccountIds)
            ->first();

        if ($account) {
            return $account;
        }

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
