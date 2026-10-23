<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\BusinessUnit;
use App\Domains\HRMS\Models\CashAdvance;
use App\Domains\HRMS\Models\Company;
use App\Domains\HRMS\Models\Department;
use App\Domains\HRMS\Models\Designation;
use App\Domains\HRMS\Models\Employee;
use App\Domains\HRMS\Models\ExpenseApprovalWorkflow;
use App\Domains\HRMS\Models\ExpenseCategory;
use App\Domains\HRMS\Models\ExpenseClaim;
use App\Domains\HRMS\Models\ExpensePolicy;
use App\Domains\HRMS\Models\ExpenseReport;
use App\Domains\HRMS\Models\TravelRequest;
use App\Domains\HRMS\Services\HrmsScopeService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TravelExpenseRepository implements TravelExpenseRepositoryInterface
{
    public function __construct(
        private readonly HrmsScopeService $scopeService
    ) {}

    public static function currencySymbol(?string $code): string
    {
        return match (strtoupper($code ?? 'USD')) {
            'INR' => '₹',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            'AED' => 'AED ',
            'SAR' => 'SAR ',
            'AUD' => 'A$',
            'CAD' => 'C$',
            'SGD' => 'S$',
            default => '$',
        };
    }

    public function getIndexData(array $inputs, ?User $user, ?int $tenantId): array
    {
        $employee = Employee::resolveForUser($user);
        if (!$employee && $user && $user->email) {
            $employee = Employee::where('personal_email', $user->email)
                ->orWhere('office_email', $user->email)
                ->first();
        }

        $company = Company::first();
        $currencyCode = $company?->currency ?? 'USD';
        $currencySymbol = self::currencySymbol($currencyCode);

        $employees = Employee::where('status', true)->orderBy('full_name')->get();
        $categories = ExpenseCategory::where('tenant_id', $tenantId)->where('status', true)->orderBy('name')->get();
        $designations = Designation::where('status', true)->orderBy('name')->get();

        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.travel_expenses.approve'));
        $isAdmin = $isHrAdmin;

        $activeTab = $inputs['tab'] ?? 'travel';
        $travelPageName = ($activeTab === 'travel') ? 'page' : 'travel_page';
        $advancePageName = ($activeTab === 'advance') ? 'page' : 'advance_page';
        $reportPageName = ($activeTab === 'report') ? 'page' : 'report_page';

        $travelSearch = $inputs['travel_search'] ?? null;
        $travelStatus = $inputs['travel_status'] ?? null;
        $travelSort = $inputs['travel_sort'] ?? 'newest';

        $travelQuery = TravelRequest::where('tenant_id', $tenantId)->with(['employee', 'expenseReports', 'cashAdvances']);
        if (!$isHrAdmin) {
            $this->scopeService->applyRelatedScope($travelQuery, $user);
        }
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

        $advanceSearch = $inputs['advance_search'] ?? null;
        $advanceStatus = $inputs['advance_status'] ?? null;
        $advanceSort = $inputs['advance_sort'] ?? 'newest';

        $advanceQuery = CashAdvance::where('tenant_id', $tenantId)->with(['employee', 'travelRequest']);
        if (!$isHrAdmin) {
            $this->scopeService->applyRelatedScope($advanceQuery, $user);
        }
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

        $reportSearch = $inputs['report_search'] ?? null;
        $reportStatus = $inputs['report_status'] ?? null;
        $reportSort = $inputs['report_sort'] ?? 'newest';

        $reportQuery = ExpenseReport::where('tenant_id', $tenantId)->with(['employee', 'travelRequest', 'claims', 'cashAdvance']);
        if (!$isHrAdmin) {
            $this->scopeService->applyRelatedScope($reportQuery, $user);
        }
        if ($reportSearch) {
            $reportQuery->where(function($q) use ($reportSearch) {
                $q->where('title', 'like', "%{$reportSearch}%")
                  ->orWhere('id', 'like', "%{$reportSearch}%")
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

        $stats = [
            'pending_travel'  => TravelRequest::where('tenant_id', $tenantId)->whereIn('status', ['pending', 'l1_approved'])->count(),
            'pending_advance' => CashAdvance::where('tenant_id', $tenantId)->whereIn('status', ['pending', 'l1_approved'])->count(),
            'pending_reports' => ExpenseReport::where('tenant_id', $tenantId)->whereIn('status', ['submitted', 'l1_approved'])->count(),
            'total_claims'    => ExpenseClaim::whereHas('report', fn($q) => $q->where('tenant_id', $tenantId))->sum('amount'),
        ];

        return compact(
            'travelRequests',
            'cashAdvances',
            'expenseReports',
            'employees',
            'categories',
            'designations',
            'employee',
            'isHrAdmin',
            'isAdmin',
            'stats',
            'currencySymbol',
            'activeTab'
        );
    }

    public function storeTravelRequest(array $validated, ?int $tenantId): TravelRequest
    {
        return TravelRequest::create([
            'tenant_id'              => $tenantId,
            'employee_id'            => $validated['employee_id'],
            'purpose'                => $validated['purpose'],
            'destination'            => $validated['destination'],
            'start_date'             => $validated['start_date'],
            'end_date'               => $validated['end_date'],
            'estimated_budget'       => $validated['estimated_budget'] ?? $validated['estimated_cost'] ?? 0,
            'approved_budget'        => $validated['approved_budget'] ?? null,
            'status'                 => $validated['status'] ?? 'pending',
            'approval_levels'        => $validated['approval_levels'] ?? 1,
            'current_approval_level' => 1,
        ]);
    }

    public function updateTravelStatus(TravelRequest $travelRequest, array $validated, ?User $user): bool
    {
        $status = $validated['status'];
        $fields = ['status' => $status];

        if (isset($validated['approved_budget'])) {
            $fields['approved_budget'] = $validated['approved_budget'];
        }

        if ($status === 'approved') {
            $fields['l2_approved_by'] = $user?->id;
            $fields['l2_approved_at'] = Carbon::now();
            $fields['current_approval_level'] = 2;
        } elseif ($status === 'l1_approved') {
            $fields['l1_approved_by'] = $user?->id;
            $fields['l1_approved_at'] = Carbon::now();
            $fields['current_approval_level'] = 2;
        }

        return $travelRequest->update($fields);
    }

    public function storeCashAdvance(array $validated, ?int $tenantId): CashAdvance
    {
        return CashAdvance::create([
            'tenant_id'              => $tenantId,
            'employee_id'            => $validated['employee_id'],
            'travel_request_id'      => $validated['travel_request_id'] ?? null,
            'amount'                 => $validated['amount'],
            'approved_amount'        => $validated['approved_amount'] ?? null,
            'purpose'                => $validated['purpose'],
            'status'                 => $validated['status'] ?? 'pending',
            'approval_levels'        => $validated['approval_levels'] ?? 1,
            'current_approval_level' => 1,
        ]);
    }

    public function updateAdvanceStatus(CashAdvance $cashAdvance, array $validated, ?User $user): bool
    {
        $status = $validated['status'];
        $fields = ['status' => $status];

        if (isset($validated['approved_amount'])) {
            $fields['approved_amount'] = $validated['approved_amount'];
        }

        if ($status === 'approved') {
            $fields['l2_approved_by'] = $user?->id;
            $fields['l2_approved_at'] = Carbon::now();
            $fields['current_approval_level'] = 2;
        } elseif ($status === 'l1_approved') {
            $fields['l1_approved_by'] = $user?->id;
            $fields['l1_approved_at'] = Carbon::now();
            $fields['current_approval_level'] = 2;
        } elseif ($status === 'disbursed') {
            $fields['disbursed_at'] = Carbon::now();
            $fields['disbursed_by'] = $user?->id;
        }

        return $cashAdvance->update($fields);
    }

    public function storeExpenseReport(array $validated, ?int $tenantId): ExpenseReport
    {
        $totalAmount = $validated['total_amount'] ?? 0;
        $advanceAdjusted = $validated['advance_adjusted'] ?? 0;
        $netReimbursement = $validated['net_reimbursement'] ?? max($totalAmount - $advanceAdjusted, 0);

        return ExpenseReport::create([
            'tenant_id'              => $tenantId,
            'employee_id'            => $validated['employee_id'],
            'travel_request_id'      => $validated['travel_request_id'] ?? null,
            'title'                  => $validated['title'],
            'total_amount'           => $totalAmount,
            'advance_adjusted'       => $advanceAdjusted,
            'net_reimbursement'      => $netReimbursement,
            'approved_amount'        => $validated['approved_amount'] ?? null,
            'payout_channel'         => $validated['payout_channel'] ?? 'accounting',
            'status'                 => $validated['status'] ?? 'draft',
            'approval_levels'        => $validated['approval_levels'] ?? 1,
            'current_approval_level' => 1,
        ]);
    }

    public function getReportShowData(int $id, ?User $user, ?int $tenantId): array
    {
        $report = ExpenseReport::where('tenant_id', $tenantId)
            ->with(['employee', 'travelRequest', 'cashAdvance', 'claims.category'])
            ->findOrFail($id);

        $categories = ExpenseCategory::where('tenant_id', $tenantId)->where('status', true)->get();
        $isHrAdmin = $user && ($user->hasHrPermission('hr.settings.manage') || $user->hasHrPermission('hrms.travel_expenses.approve'));
        
        $company = Company::first();
        $currencySymbol = self::currencySymbol($company?->currency ?? 'USD');

        return compact('report', 'categories', 'isHrAdmin', 'currencySymbol');
    }

    public function updateReportStatus(ExpenseReport $report, array $validated, ?User $user): bool
    {
        $status = $validated['status'];
        $fields = ['status' => $status];

        if (isset($validated['approved_amount'])) {
            $fields['approved_amount'] = $validated['approved_amount'];
        }

        if ($status === 'approved') {
            $fields['l2_approved_by'] = $user?->id;
            $fields['l2_approved_at'] = Carbon::now();
            $fields['current_approval_level'] = 2;
            $fields['approved_amount'] = $validated['approved_amount'] ?? $report->total_amount;
        } elseif ($status === 'l1_approved') {
            $fields['l1_approved_by'] = $user?->id;
            $fields['l1_approved_at'] = Carbon::now();
            $fields['current_approval_level'] = 2;
        }

        return $report->update($fields);
    }

    public function addClaim(ExpenseReport $report, array $validated, Request $request, ?int $tenantId): ExpenseClaim
    {
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')->storeTenantFile('expense_receipts', 'public');
        }

        $claim = ExpenseClaim::create([
            'expense_report_id'   => $report->id,
            'expense_category_id' => $validated['expense_category_id'],
            'expense_date'        => $validated['expense_date'] ?? $validated['claim_date'] ?? Carbon::now(),
            'amount'              => $validated['amount'],
            'tax_amount'          => $validated['tax_amount'] ?? $validated['tax'] ?? 0.00,
            'merchant'            => $validated['merchant'] ?? null,
            'description'         => $validated['description'] ?? null,
            'receipt_path'        => $receiptPath,
            'status'              => $validated['status'] ?? 'draft',
        ]);

        $report->increment('total_amount', $validated['amount']);

        return $claim;
    }

    public function deleteClaim(ExpenseClaim $claim): bool
    {
        $report = $claim->expenseReport;
        $amount = $claim->amount;
        $deleted = (bool) $claim->delete();

        if ($deleted && $report) {
            $report->decrement('total_amount', $amount);
        }

        return $deleted;
    }

    public function settleReport(ExpenseReport $report, array $validated, ?User $user): bool
    {
        return $report->update([
            'status'          => 'paid',
            'approved_amount' => $validated['approved_amount'] ?? $report->total_amount,
        ]);
    }

    public function getPolicyIndexData(array $inputs, ?int $tenantId): array
    {
        $activeTab = $inputs['tab'] ?? 'categories';

        $filters = [
            'search' => $inputs['search'] ?? '',
            'status' => $inputs['status'] ?? '',
            'sort'   => $inputs['sort'] ?? 'name_asc',
        ];

        $catFilters = [
            'search' => $inputs['cat_search'] ?? '',
            'status' => $inputs['cat_status'] ?? '',
            'sort'   => $inputs['cat_sort'] ?? 'name_asc',
        ];

        $workflowFilters = [
            'search' => $inputs['wf_search'] ?? '',
            'status' => $inputs['wf_status'] ?? '',
            'sort'   => $inputs['wf_sort'] ?? 'name_asc',
        ];

        $policiesQuery = ExpensePolicy::where('tenant_id', $tenantId)->with(['rules.category', 'company', 'branch']);
        if (!empty($filters['search'])) {
            $policiesQuery->where('name', 'like', "%{$filters['search']}%");
        }
        if (!empty($filters['status'])) {
            $policiesQuery->where('status', $filters['status'] === 'active');
        }
        $policiesQuery->orderBy('name', $filters['sort'] === 'name_desc' ? 'desc' : 'asc');
        $policies = $policiesQuery->paginate(10, ['*'], 'policies_page')->withQueryString();

        $categoriesQuery = ExpenseCategory::where('tenant_id', $tenantId);
        if (!empty($catFilters['search'])) {
            $categoriesQuery->where('name', 'like', "%{$catFilters['search']}%");
        }
        if (!empty($catFilters['status'])) {
            $categoriesQuery->where('status', $catFilters['status'] === 'active');
        }
        $categoriesQuery->orderBy('name', $catFilters['sort'] === 'name_desc' ? 'desc' : 'asc');
        $categories = $categoriesQuery->paginate(10, ['*'], 'categories_page')->withQueryString();

        $workflowsQuery = ExpenseApprovalWorkflow::where('tenant_id', $tenantId)->with(['company', 'department']);
        if (!empty($workflowFilters['search'])) {
            $workflowsQuery->where('name', 'like', "%{$workflowFilters['search']}%");
        }
        if (!empty($workflowFilters['status'])) {
            $workflowsQuery->where('status', $workflowFilters['status'] === 'active');
        }
        $workflowsQuery->orderBy('name', $workflowFilters['sort'] === 'name_desc' ? 'desc' : 'asc');
        $workflows = $workflowsQuery->paginate(10, ['*'], 'workflows_page')->withQueryString();

        $allCategories = ExpenseCategory::where('tenant_id', $tenantId)->where('status', true)->get();
        $departments = Department::where('tenant_id', $tenantId)->orderBy('name')->get();
        $designations = Designation::where('tenant_id', $tenantId)->orderBy('name')->get();
        $companies = Company::where('tenant_id', $tenantId)->orderBy('name')->get();
        $branches = Branch::where('tenant_id', $tenantId)->orderBy('name')->get();
        $businessUnits = BusinessUnit::where('tenant_id', $tenantId)->orderBy('name')->get();

        return compact(
            'policies',
            'categories',
            'workflows',
            'allCategories',
            'departments',
            'designations',
            'companies',
            'branches',
            'businessUnits',
            'filters',
            'catFilters',
            'workflowFilters',
            'activeTab'
        );
    }
}
