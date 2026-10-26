<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Policies\VoucherPolicy;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\VoucherService;
use App\Domains\Accounting\Support\BillWiseInput;
use App\Domains\Accounting\Support\VoucherType;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class VoucherController extends Controller
{
    public function __construct(
        private readonly VoucherService $vouchers,
        private readonly ChartOfAccountsService $accounts,
        private readonly VoucherPolicy $voucherPolicy,
        private readonly JournalService $journals,
        private readonly BillWiseInput $billWise,
    ) {
    }

    public function index(Request $request, string $type): View
    {
        $this->assertValidType($type);
        abort_unless($this->voucherPolicy->viewAny($request->user(), $type), 403);

        $filters = $request->only(['status', 'posted_by', 'from', 'to', 'search', 'sort', 'direction']);
        $vouchers = $this->vouchers->paginate($type, $filters, 15);
        $canCreate = $this->voucherPolicy->post($request->user(), $type);

        return view('modules.accounting.vouchers.index', [
            'type' => $type,
            'label' => VoucherType::label($type),
            'vouchers' => $vouchers,
            'filters' => $filters,
            'posters' => $this->journals->posters(),
            'canCreate' => $canCreate,
            // Only fetched when the user can actually post — these back the
            // create-voucher drawer opened from this same page.
            'accounts' => $canCreate ? $this->accounts->active() : collect(),
            'cashBankAccounts' => $canCreate ? $this->accounts->active()->where('is_cash_or_bank', true)->values() : collect(),
            'partyLedgers' => $canCreate ? $this->partyLedgers($type) : [],
            'summary' => [
                'total' => Journal::query()->where('voucher_type', $type)->count(),
                'posted' => Journal::query()->where('voucher_type', $type)->where('status', Journal::STATUS_POSTED)->count(),
                'draft' => Journal::query()->where('voucher_type', $type)->where('status', Journal::STATUS_DRAFT)->count(),
                'pending' => Journal::query()->where('voucher_type', $type)->where('status', Journal::STATUS_PENDING_APPROVAL)->count(),
                'reversed' => Journal::query()->where('voucher_type', $type)->where('status', Journal::STATUS_REVERSED)->count(),
            ],
        ]);
    }

    public function create(Request $request, string $type): View
    {
        $this->assertValidType($type);
        abort_unless($this->voucherPolicy->post($request->user(), $type), 403);

        return view('modules.accounting.vouchers.create', [
            'type' => $type,
            'label' => VoucherType::label($type),
            'accounts' => $this->accounts->active(),
            'cashBankAccounts' => $this->accounts->active()->where('is_cash_or_bank', true)->values(),
            'partyLedgers' => $this->partyLedgers($type),
        ]);
    }

    /**
     * The ledger a party's line goes to when the voucher's Party is picked and
     * that line has no ledger yet (party type => account id).
     *
     * @return array<string, int>
     */
    private function partyLedgers(string $type): array
    {
        $tenantId = (int) require_tenant_id();
        $systemAccounts = app(\App\Domains\Accounting\Services\SystemAccountService::class);
        $payables = $systemAccounts->get(\App\Domains\Accounting\Support\SystemAccount::AP, $tenantId);
        // Paying an employee is usually salary; money back from one, an advance returned.
        $employee = $systemAccounts->get($type === VoucherType::RECEIPT ? \App\Domains\Accounting\Support\SystemAccount::ADVANCE_TO_EMPLOYEES : 'salary_payable', $tenantId);

        return array_filter([
            'customer' => $systemAccounts->get(\App\Domains\Accounting\Support\SystemAccount::AR, $tenantId)?->id,
            'vendor' => $payables?->id,
            'transporter' => $payables?->id,
            'employee' => $employee?->id,
        ]);
    }

    public function store(Request $request, string $type): RedirectResponse
    {
        $this->assertValidType($type);
        abort_unless($this->voucherPolicy->post($request->user(), $type), 403);

        $validated = $request->validate([
            'voucher_date' => ['required', 'date'],
            'memo' => ['nullable', 'string', 'max:500'],
            'party_name' => ['nullable', 'string', 'max:255'],
            // The voucher's Party ("type:id"); empty when only a name was typed.
            'party' => ['nullable', 'string', 'max:40'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:2', 'max:20'],
            'items.*.chart_of_account_id' => ['required', 'integer', tenant_exists('chart_of_accounts')],
            'items.*.debit' => ['nullable', 'numeric', 'min:0'],
            'items.*.credit' => ['nullable', 'numeric', 'min:0'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            ...BillWiseInput::rules(),
        ]);

        // The voucher's Party tags each party-ledger line that wasn't given one
        // in its own bill-wise box (like picking the party ledger in Tally).
        $voucherParty = trim((string) ($validated['party'] ?? ''));
        if ($voucherParty !== '') {
            $validated['items'] = $this->billWise->withVoucherParty($validated['items'], $voucherParty);
        }

        // Party per line and the bills it settles / opens (the bill-wise box).
        [$lines, $billHints] = $this->billWise->apply($validated['items']);
        $firstParty = collect($lines)->first(fn ($line) => ! empty($line['party_id']));
        $partyName = $validated['party_name'] ?? null;
        if ($voucherParty !== '') {
            [$partyType, $partyId] = explode(':', $voucherParty, 2);
            $partyName = $this->billWise->partyName($partyType, (int) $partyId);
        } elseif ($firstParty && $partyName === null) {
            $partyName = $this->billWise->partyName($firstParty['party_type'], (int) $firstParty['party_id']);
        }

        try {
            if ($type === VoucherType::CONTRA) {
                $this->assertBothLinesAreCashOrBank($lines);
            }

            $journal = $this->vouchers->post($type, $lines, [
                'journal_date' => $validated['voucher_date'],
                'memo' => $validated['memo'] ?? null,
                'party_type' => $firstParty['party_type'] ?? null,
                'party_id' => $firstParty['party_id'] ?? null,
                'party_name' => $partyName,
                'bill_allocations' => $billHints,
                'payment_method' => $validated['payment_method'] ?? null,
                'reference_no' => $validated['reference_no'] ?? null,
                'posted_by' => auth()->id(),
                // Keyed in by a person: may wait for maker-checker approval.
                'through_approval' => true,
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()->route("accounting.vouchers.{$type}.show", $journal)
            ->with('success', $journal->isPendingApproval()
                ? VoucherType::label($type) . " {$journal->journal_number} saved and sent for approval. It will post once someone else approves it."
                : VoucherType::label($type) . ' posted successfully.');
    }

    // Note: $journal must precede $type in this signature — 'journal' is a real
    // URI-matched route parameter while 'type' is only a route default, and
    // Laravel's controller dependency resolver positionally aligns remaining
    // scalar parameters against the route's parameter array in that order.
    public function show(Request $request, Journal $journal, string $type): View
    {
        $this->assertValidType($type);
        abort_unless($journal->voucher_type === $type, 404);
        abort_unless($this->voucherPolicy->view($request->user(), $journal, $type), 403);

        return view('modules.accounting.vouchers.show', [
            'type' => $type,
            'label' => VoucherType::label($type),
            'journal' => $this->vouchers->find($type, $journal->id),
            'canReverse' => $this->voucherPolicy->reverse($request->user(), $journal),
            'canApprove' => $journal->isPendingApproval()
                && $request->user()->can('approve', $journal)
                && (int) $journal->posted_by !== (int) $request->user()->id,
        ]);
    }

    // See the parameter-order note on show() above — $journal must precede $type here too.
    public function reverse(Request $request, Journal $journal, string $type): RedirectResponse
    {
        $this->assertValidType($type);
        abort_unless($journal->voucher_type === $type, 404);
        abort_unless($this->voucherPolicy->reverse($request->user(), $journal), 403);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->journals->reverse($journal->id, $validated['reason'] ?? null, auth()->id());
        } catch (InvalidArgumentException $e) {
            return redirect()->route("accounting.vouchers.{$type}.show", $journal)->with('error', $e->getMessage());
        }

        return redirect()->route("accounting.vouchers.{$type}.show", $journal)
            ->with('success', VoucherType::label($type) . ' reversed successfully.');
    }

    private function assertValidType(string $type): void
    {
        abort_unless(VoucherType::isValid($type), 404);
    }

    private function assertBothLinesAreCashOrBank(array $items): void
    {
        $ids = collect($items)->pluck('chart_of_account_id')->unique();

        $count = ChartOfAccount::whereIn('id', $ids)->where('is_cash_or_bank', true)->count();

        if ($count !== $ids->count()) {
            throw new InvalidArgumentException('Every line of a Contra voucher must be a cash or bank account.');
        }
    }
}
