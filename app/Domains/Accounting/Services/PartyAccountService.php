<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\BillAllocation;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use Illuminate\Support\Carbon;

/**
 * A party's account as the books see it, for other modules' screens (the
 * vendor and customer pages). Read only, and the same figures as Party
 * Ledger, Bills Outstanding and ageing, so every screen shows one balance:
 * bills and payments from any module, opening dues, assets bought on credit,
 * vouchers and settlements all count.
 *
 * Amounts are signed the way the party's page reads them: positive is what a
 * customer owes us, or what we owe a vendor / employee / transporter.
 */
class PartyAccountService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly FiscalPeriodService $periods,
        private readonly BillAllocationService $bills,
    ) {
    }

    /** False when the tenant has no Accounting (or no bill register yet): callers keep their own document totals. */
    public function available(): bool
    {
        $modules = tenant_allowed_modules();

        return ($modules === null || in_array('accounting', $modules, true)) && BillAllocationService::enabled();
    }

    /**
     * The party's statement: balance brought forward to the start of the
     * financial year (unless $from is given), then every posted line.
     *
     * @return array{from: Carbon, to: Carbon, opening: float, closing: float, rows: list<array<string, mixed>>}
     */
    public function statement(string $partyType, int $partyId, ?\DateTimeInterface $from = null, ?\DateTimeInterface $to = null): array
    {
        $from = Carbon::parse($from ?? $this->periods->periodForDate(now())?->fiscalYear?->start_date ?? now()->startOfYear())->startOfDay();
        $to = Carbon::parse($to ?? now())->endOfDay();

        $ledger = $this->journals->partyLedger($partyType, $partyId, $from, $to);

        return [
            'from' => $from,
            'to' => $to,
            'opening' => $ledger['opening'],
            'closing' => $ledger['closing'],
            'rows' => $ledger['entries']->map(fn (array $row) => [
                'date' => $row['entry']->journal->journal_date,
                'journal_number' => $row['entry']->journal->journal_number,
                'description' => $row['entry']->description ?: $row['entry']->journal->memo,
                'bill_refs' => $row['bill_refs'],
                'debit' => round((float) $row['entry']->debit, 2),
                'credit' => round((float) $row['entry']->credit, 2),
                'running_balance' => $row['running_balance'],
                'url' => route('accounting.journals.show', $row['entry']->journal_id),
            ])->values()->all(),
        ];
    }

    /**
     * What is due across every party of a type today (customers' receivables,
     * or what we owe vendors): each party's ledger balance, advances left out.
     */
    public function totalOutstanding(string $partyType): float
    {
        $sign = $partyType === JournalEntry::PARTY_CUSTOMER ? 1 : -1;

        $balances = JournalEntry::query()
            ->where('party_type', $partyType)
            ->whereNotNull('party_id')
            ->whereHas('journal', fn ($q) => $q
                ->whereIn('status', [\App\Domains\Accounting\Models\Journal::STATUS_POSTED, \App\Domains\Accounting\Models\Journal::STATUS_REVERSED])
                ->whereDate('journal_date', '<=', now()->toDateString()))
            ->groupBy('party_id')
            ->selectRaw('party_id, SUM(debit) - SUM(credit) as balance')
            ->pluck('balance');

        return round($balances->sum(fn ($balance) => max(0.0, $sign * (float) $balance)), 2);
    }

    /**
     * Headline figures for the party.
     *
     *  - balance:     signed ledger balance today (negative = advance / credit in the party's favour)
     *  - outstanding: what is due (0 when the balance is in the party's favour)
     *  - advance:     what the party has in hand with us (0 when something is due)
     *  - billed:      every bill opened for the party (invoices / vendor bills, opening dues, credit purchases)
     *  - paid:        everything that reduced those bills (payments, receipts, returns, settlements)
     *  - bills:       how many bills that is
     *  - overdue:     what is still open on bills past their due date
     *
     * @return array{balance: float, outstanding: float, advance: float, billed: float, paid: float, bills: int, overdue: float}
     */
    public function summary(string $partyType, int $partyId): array
    {
        $sign = $partyType === JournalEntry::PARTY_CUSTOMER ? 1 : -1;
        $owedLedgers = $this->bills->controlIdsFor($sign === 1 ? 'receivable' : 'payable');

        $balance = $this->statement($partyType, $partyId)['closing'];

        $register = BillAllocation::query()->where('party_type', $partyType)->where('party_id', $partyId);
        // Bills opened, net of any reversed ones (a reversal mirrors its New Ref).
        $opened = (clone $register)->whereIn('control_account_id', $owedLedgers)->where('ref_type', BillAllocation::NEW_REF)
            ->groupBy('bill_reference_id')->selectRaw('bill_reference_id, SUM(amount) as amount')
            ->havingRaw('ABS(SUM(amount)) >= 0.005')->pluck('amount');
        $billed = round($sign * (float) $opened->sum(), 2);
        // Everything on the party's bill-wise ledgers (advances paid included).
        $registerBalance = round($sign * (float) (clone $register)->sum('amount'), 2);

        $today = now()->startOfDay();
        $overdue = $this->bills->openBills($sign === 1 ? 'receivable' : 'payable', null, $partyType, $partyId)
            ->filter(fn ($open) => $sign * $open->outstanding > 0 && $open->bill->due_date && Carbon::parse($open->bill->due_date)->lt($today))
            ->sum(fn ($open) => $sign * $open->outstanding);

        return [
            'balance' => $balance,
            'outstanding' => max(0.0, $balance),
            'advance' => $balance < 0 ? abs($balance) : 0.0,
            'billed' => $billed,
            'paid' => round($billed - $registerBalance, 2),
            'bills' => $opened->count(),
            'overdue' => round((float) $overdue, 2),
        ];
    }
}
