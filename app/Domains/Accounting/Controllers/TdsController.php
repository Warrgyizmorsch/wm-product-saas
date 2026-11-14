<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\TdsChallan;
use App\Domains\Accounting\Models\TdsConfiguration;
use App\Domains\Accounting\Models\TdsDeduction;
use App\Domains\Accounting\Models\TdsSection;
use App\Domains\Accounting\Services\Tds\TdsChallanService;
use App\Domains\Accounting\Services\Tds\TdsReturnService;
use App\Domains\Accounting\Services\Tds\TdsSectionService;
use App\Domains\Sales\Models\CustomerPayment;
use App\Exports\AccountingReportExport;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;

/**
 * TDS (non-salary): section master and deductor settings, the deduction
 * register with challan deposits, the 26Q quarter and Form 16A, and the TDS
 * customers withheld from us (receivable).
 */
class TdsController extends Controller
{
    public function __construct(
        private readonly AccessService $access,
        private readonly TdsSectionService $sections,
        private readonly TdsChallanService $challans,
        private readonly TdsReturnService $returns,
    ) {
    }

    public function setup(): View
    {
        $this->authorizeTds('accounting.tds.view');
        $this->sections->provisionDefaultsIfMissing(require_tenant_id());

        return view('modules.accounting.tds.setup', [
            'sections' => TdsSection::query()->with('payableAccount')->orderBy('code')->orderBy('name')->get(),
            'config' => TdsConfiguration::current() ?? new TdsConfiguration(),
            'canConfigure' => $this->can('accounting.tds.configure'),
        ]);
    }

    public function updateSection(Request $request, int $section): RedirectResponse
    {
        $this->authorizeTds('accounting.tds.configure');

        $row = TdsSection::query()->findOrFail($section);
        $row->update($request->validate([
            'name' => 'required|string|max:255',
            'nature_of_payment' => 'nullable|string|max:255',
            'rate_individual' => 'required|numeric|min:0|max:100',
            'rate_other' => 'required|numeric|min:0|max:100',
            'rate_no_pan' => 'required|numeric|min:0|max:100',
            'single_threshold' => 'nullable|numeric|min:0',
            'aggregate_threshold' => 'nullable|numeric|min:0',
            'threshold_period' => 'required|in:per_transaction,annual,monthly',
            'deduct_on_excess_only' => 'boolean',
            'is_active' => 'boolean',
        ]));

        return back()->with('success', "Section {$row->code} updated.");
    }

    public function updateSettings(Request $request): RedirectResponse
    {
        $this->authorizeTds('accounting.tds.configure');

        $data = $request->validate([
            'tan' => ['nullable', 'string', 'regex:/^[A-Za-z]{4}[0-9]{5}[A-Za-z]$/'],
            'deductor_type' => 'nullable|string|max:30',
            'deductor_name' => 'nullable|string|max:255',
            'deductor_pan' => ['nullable', 'string', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'responsible_person_name' => 'nullable|string|max:255',
            'responsible_person_designation' => 'nullable|string|max:255',
            'responsible_person_pan' => ['nullable', 'string', 'regex:/^[A-Za-z]{5}[0-9]{4}[A-Za-z]$/'],
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|max:255',
            'pincode' => 'nullable|string|max:10',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
        ], ['tan.regex' => 'A TAN looks like ABCD12345E.', '*.regex' => 'A PAN looks like ABCDE1234F.']);

        foreach (['tan', 'deductor_pan', 'responsible_person_pan'] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? strtoupper($data[$field]) : null;
        }

        $config = TdsConfiguration::current() ?? new TdsConfiguration(['tenant_id' => require_tenant_id()]);
        $config->fill($data)->save();

        return back()->with('success', 'TDS deductor details saved.');
    }

    public function register(Request $request): View
    {
        $this->authorizeTds('accounting.tds.view');
        $this->sections->provisionDefaultsIfMissing(require_tenant_id());

        $fy = $request->input('fy', TdsDeduction::fiscalYearFor(now()));
        $quarter = $request->integer('quarter') ?: null;
        $status = $request->input('status', 'all'); // all | undeposited | deposited | below_threshold

        $deductions = TdsDeduction::query()->live()
            ->with(['section', 'challan'])
            ->where('fiscal_year', $fy)
            ->when($quarter, fn ($q) => $q->where('quarter', $quarter))
            ->when($request->filled('section'), fn ($q) => $q->where('tds_section_id', $request->integer('section')))
            ->when($status === 'undeposited', fn ($q) => $q->where('tds_amount', '>', 0)->whereNull('tds_challan_id'))
            ->when($status === 'deposited', fn ($q) => $q->whereNotNull('tds_challan_id'))
            ->when($status === 'below_threshold', fn ($q) => $q->where('reason', TdsDeduction::REASON_BELOW_THRESHOLD))
            ->orderBy('deduction_date')->orderBy('id')
            ->paginate(100)->withQueryString();

        return view('modules.accounting.tds.register', [
            'deductions' => $deductions,
            'fy' => $fy,
            'quarter' => $quarter,
            'status' => $status,
            'sections' => TdsSection::query()->orderBy('code')->orderBy('name')->get(),
            'fiscalYears' => $this->fiscalYears(),
            'challans' => TdsChallan::query()->with('bankAccount')->latest('deposit_date')->limit(25)->get(),
            'bankAccounts' => ChartOfAccount::query()->where('is_cash_or_bank', true)->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name']),
            'undepositedTotal' => (float) TdsDeduction::query()->undeposited()->sum('tds_amount'),
            'canDeposit' => $this->can('accounting.tds.challans.create'),
        ]);
    }

    public function storeChallan(Request $request): RedirectResponse
    {
        $this->authorizeTds('accounting.tds.challans.create');

        $data = $request->validate([
            'challan_number' => 'required|string|max:30',
            'bsr_code' => ['nullable', 'string', 'regex:/^[0-9]{7}$/'],
            'cin' => 'nullable|string|max:30',
            'deposit_date' => 'required|date',
            'interest' => 'nullable|numeric|min:0',
            'late_fee' => 'nullable|numeric|min:0',
            'bank_account_id' => 'required|integer',
            'deduction_ids' => 'required|array|min:1',
            'deduction_ids.*' => 'integer',
            'notes' => 'nullable|string|max:1000',
            'already_paid' => 'nullable|boolean',
        ], ['bsr_code.regex' => 'The BSR code is the bank branch\'s 7-digit code.', 'deduction_ids.required' => 'Tick the deductions this challan pays.']);

        try {
            $challan = $this->challans->deposit([
                ...$data,
                'tenant_id' => require_tenant_id(),
                'company_id' => company_id(),
                'branch_id' => branch_id(),
                'created_by' => auth()->id(),
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['challan' => $e->getMessage()]);
        }

        return back()->with('success', "Challan {$challan->challan_number} recorded: " . format_currency($challan->totalAmount()) . ' deposited.');
    }

    public function returns(Request $request): View
    {
        $this->authorizeTds('accounting.tds.returns.view');

        [$fy, $quarter] = $this->period($request);
        $form = $this->form($request);
        $this->sections->salarySection(require_tenant_id());

        return view('modules.accounting.tds.returns', [
            ...$this->returns->quarter($fy, $quarter, $form),
            'fiscalYears' => $this->fiscalYears(),
            // 26Q: vendors for Form 16A (quarterly). 24Q: employees for Form 16 (yearly).
            'deductees' => $form === TdsReturnService::FORM_24Q ? $this->returns->annualSalary($fy) : $this->returns->deducteesFor($fy, $quarter),
            'canExport' => $this->can('accounting.tds.returns.export'),
        ]);
    }

    public function exportReturn(Request $request, string $format): Response
    {
        $this->authorizeTds('accounting.tds.returns.export');

        [$fy, $quarter] = $this->period($request);
        $form = $this->form($request);
        $table = $this->returns->exportTable($this->returns->quarter($fy, $quarter, $form));
        $filename = "Form{$form}_{$fy}_Q{$quarter}";

        if ($format === 'pdf') {
            return Pdf::loadView('modules.accounting.reports.export-pdf', ['report' => $table])
                ->setPaper('a4', 'landscape')
                ->download($filename . '.pdf');
        }

        return Excel::download(new AccountingReportExport($table), $filename . '.xlsx');
    }

    public function form16a(Request $request, int $vendor): Response
    {
        $this->authorizeTds('accounting.tds.returns.export');

        [$fy, $quarter] = $this->period($request);
        $data = $this->returns->form16a($vendor, $fy, $quarter);
        abort_if($data['vendor'] === null || $data['deductions']->isEmpty(), 404);

        return Pdf::loadView('modules.accounting.tds.form16a-pdf', [...$data, 'quarterLabel' => TdsReturnService::QUARTERS[$quarter]])
            ->setPaper('a4')
            ->download("Form16A_{$fy}_Q{$quarter}_" . preg_replace('/[^A-Za-z0-9]+/', '_', (string) $data['vendor']->name) . '.pdf');
    }

    /** Form 16 (Part A summary + salary totals) for one employee and financial year. */
    public function form16(Request $request, int $employee): Response
    {
        $this->authorizeTds('accounting.tds.returns.export');

        [$fy] = $this->period($request);
        $data = $this->returns->form16($employee, $fy);
        abort_if($data['employee'] === null || $data['months']->isEmpty(), 404);

        return Pdf::loadView('modules.accounting.tds.form16-pdf', $data)
            ->setPaper('a4')
            ->download("Form16_{$fy}_" . preg_replace('/[^A-Za-z0-9]+/', '_', (string) $data['employee']->full_name) . '.pdf');
    }

    public function receivable(Request $request): View
    {
        $this->authorizeTds('accounting.tds.view');

        $fy = $request->input('fy', TdsDeduction::fiscalYearFor(now()));
        [$from, $to] = $this->fiscalYearRange($fy);

        $receipts = CustomerPayment::query()->with('customer')
            ->where('tds_amount', '>', 0)
            ->whereBetween('payment_date', [$from, $to])
            ->when($request->input('certificate') === 'pending', fn ($q) => $q->where('tds_certificate_received', false))
            ->orderBy('payment_date')
            ->get();

        return view('modules.accounting.tds.receivable', [
            'receipts' => $receipts,
            'fy' => $fy,
            'fiscalYears' => $this->fiscalYears(),
            'canConfigure' => $this->can('accounting.tds.configure'),
        ]);
    }

    public function markCertificate(Request $request, int $payment): RedirectResponse
    {
        $this->authorizeTds('accounting.tds.configure');

        $row = CustomerPayment::query()->where('tds_amount', '>', 0)->findOrFail($payment);
        $row->update(['tds_certificate_received' => $request->boolean('received')]);

        return back()->with('success', "Form 16A for {$row->payment_number} marked " . ($row->tds_certificate_received ? 'received.' : 'pending.'));
    }

    /** @return array{0: string, 1: int} */
    private function period(Request $request): array
    {
        $fy = (string) $request->input('fy', TdsDeduction::fiscalYearFor(now()));
        abort_unless(preg_match('/^\d{4}-\d{2}$/', $fy) === 1, 404);
        $quarter = $request->integer('quarter') ?: TdsDeduction::quarterFor(now());
        abort_unless(isset(TdsReturnService::QUARTERS[$quarter]), 404);

        return [$fy, $quarter];
    }

    private function form(Request $request): string
    {
        return $request->input('form') === TdsReturnService::FORM_24Q ? TdsReturnService::FORM_24Q : TdsReturnService::FORM_26Q;
    }

    /** @return array{0: string, 1: string} */
    private function fiscalYearRange(string $fy): array
    {
        $start = (int) substr($fy, 0, 4);

        return ["{$start}-04-01", ($start + 1) . '-03-31'];
    }

    /** This year and the three before, newest first. */
    private function fiscalYears(): array
    {
        $start = (int) substr(TdsDeduction::fiscalYearFor(now()), 0, 4);

        return array_map(fn (int $y) => $y . '-' . substr((string) ($y + 1), -2), range($start, $start - 3));
    }

    private function can(string $permission): bool
    {
        return $this->access->allows(auth()->user(), $permission, ['tenant_id' => auth()->user()->tenant_id]);
    }

    private function authorizeTds(string $permission): void
    {
        abort_unless($this->can($permission), 403);
    }
}
