<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Policies\VoucherPolicy;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\VoucherService;
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
    ) {
    }

    public function index(Request $request, string $type): View
    {
        $this->assertValidType($type);
        abort_unless($this->voucherPolicy->viewAny($request->user(), $type), 403);

        $filters = $request->only(['status', 'search', 'sort', 'direction']);
        $vouchers = $this->vouchers->paginate($type, $filters, 15);

        return view('modules.accounting.vouchers.index', [
            'type' => $type,
            'label' => VoucherType::label($type),
            'vouchers' => $vouchers,
            'filters' => $filters,
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
            'payment_method' => ['nullable', 'string', 'max:50'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'items' => ['required', 'array', 'min:2', 'max:2'],
            'items.*.chart_of_account_id' => ['required', 'integer', 'exists:chart_of_accounts,id'],
            'items.*.debit' => ['nullable', 'numeric', 'min:0'],
            'items.*.credit' => ['nullable', 'numeric', 'min:0'],
            'items.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            if ($type === VoucherType::CONTRA) {
                $this->assertBothLinesAreCashOrBank($validated['items']);
            }

            $journal = $this->vouchers->post($type, $validated['items'], [
                'journal_date' => $validated['voucher_date'],
                'memo' => $validated['memo'] ?? null,
                'party_name' => $validated['party_name'] ?? null,
                'payment_method' => $validated['payment_method'] ?? null,
                'reference_no' => $validated['reference_no'] ?? null,
                'posted_by' => auth()->id(),
            ]);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['items' => $e->getMessage()])->withInput();
        }

        return redirect()->route("accounting.vouchers.{$type}.show", $journal)
            ->with('success', VoucherType::label($type) . ' posted successfully.');
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
            throw new InvalidArgumentException('Both lines of a Contra voucher must be cash or bank accounts.');
        }
    }
}
