<?php

namespace App\Domains\Accounting\Services\BillWise;

use App\Domains\Accounting\Models\BillAllocation;
use App\Domains\Accounting\Models\BillReference;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\AccountingAuditLogService;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\OpeningBalanceService;
use App\Domains\CRM\Models\Customer;
use App\Domains\Inventory\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Break a party's opening due into the old bills it is made of, so ageing
 * and Agst Ref work bill by bill from day one (Tally asks for this when the
 * opening is entered).
 *
 * The opening journal is not touched: its line is re-explained by one New Ref
 * per old bill. Anything already settled against the opening (a receipt Agst
 * "Opening Balance", an adjustment) is moved onto the old bills, oldest due
 * first. The breakup is saved on the master (opening_bills) so a later
 * opening re-sync rebuilds the same bills.
 */
class OpeningBreakupService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly OpeningBalanceService $openings,
        private readonly BillAllocationService $bills,
        private readonly AccountingAuditLogService $auditLog,
    ) {
    }

    /**
     * The party's posted opening due, or null when it has none (or it is an advance).
     *
     * @return array{journal: Journal, line: JournalEntry, amount: float, date: string, rows: list<array>}|null
     */
    public function describe(string $partyType, int $partyId): ?array
    {
        if (! in_array($partyType, [JournalEntry::PARTY_CUSTOMER, JournalEntry::PARTY_VENDOR], true)) {
            return null;
        }

        $journal = $this->journals->activePosting((int) tenant_id(), "{$partyType}_opening_balance", $partyId);
        $line = $journal?->entries()->where('party_type', $partyType)->where('party_id', $partyId)->first();
        if ($line === null) {
            return null;
        }

        $net = round((float) $line->debit - (float) $line->credit, 2);
        $isDue = $partyType === JournalEntry::PARTY_CUSTOMER ? $net > 0 : $net < 0;
        if (! $isDue) {
            return null;
        }

        return [
            'journal' => $journal,
            'line' => $line,
            'amount' => abs($net),
            'date' => Carbon::parse($journal->journal_date)->toDateString(),
            'rows' => $this->openings->openingBreakup($partyType, $partyId, abs($net)),
        ];
    }

    /**
     * Save the breakup (empty rows = back to one "Opening Balance" bill).
     *
     * @param list<array{ref?: string, bill_date?: string, due_date?: string, amount?: float|string}> $rows
     */
    public function split(string $partyType, int $partyId, array $rows, ?int $userId = null): void
    {
        $opening = $this->describe($partyType, $partyId);
        if ($opening === null) {
            throw new InvalidArgumentException('This party has no opening balance due to break up.');
        }

        $rows = $this->clean($rows, $opening);
        $line = $opening['line'];
        $referenceType = "{$partyType}_opening_balance";

        DB::transaction(function () use ($partyType, $partyId, $rows, $opening, $line, $referenceType, $userId) {
            $openingBills = BillReference::query()
                ->where('party_type', $partyType)->where('party_id', $partyId)
                ->where('control_account_id', $line->chart_of_account_id)
                ->where('source_type', $referenceType)->where('source_id', $partyId)
                ->pluck('id');

            $this->assertRefsFree($partyType, $partyId, (int) $line->chart_of_account_id, $openingBills->all(), $rows);

            // What already settled the opening (receipts, adjustments) — moved onto the old bills below.
            $settled = BillAllocation::query()
                ->whereIn('bill_reference_id', $openingBills)
                ->where('journal_entry_id', '!=', $line->id)
                ->orderBy('allocated_on')->orderBy('id')
                ->get();

            BillAllocation::whereIn('bill_reference_id', $openingBills)->delete();
            BillAllocation::where('journal_entry_id', $line->id)->delete();
            BillReference::whereIn('id', $openingBills)->delete();

            $model = $partyType === JournalEntry::PARTY_CUSTOMER ? Customer::class : Vendor::class;
            $model::query()->withoutGlobalScopes()->whereKey($partyId)->update(['opening_bills' => $rows === [] ? null : json_encode($rows)]);

            $this->bills->allocateJournal($opening['journal'], $this->openings->openingBillHints([
                'party_type' => $partyType,
                'party_id' => $partyId,
                'reference_type' => $referenceType,
                'reference_id' => $partyId,
            ], (float) $line->debit > 0, $opening['amount']));

            $this->repoint($settled, $partyType, $partyId, (int) $line->chart_of_account_id, $referenceType);

            $this->auditLog->record($opening['journal'], 'bill_wise.opening_breakup', 'Opening balance broken into old bills', [
                'party' => "{$partyType}:{$partyId}",
                'bills' => array_column($rows, 'ref'),
                'by' => $userId,
            ]);
        });
    }

    /** @return list<array{ref: string, bill_date: string, due_date: string, amount: float}> */
    private function clean(array $rows, array $opening): array
    {
        $clean = [];
        $refs = [];

        foreach ($rows as $row) {
            $ref = trim((string) ($row['ref'] ?? ''));
            $amount = round((float) ($row['amount'] ?? 0), 2);
            if ($ref === '' && $amount <= 0) {
                continue;
            }
            if ($ref === '' || $amount <= 0) {
                throw new InvalidArgumentException('Every old bill needs a bill number and an amount.');
            }
            if (isset($refs[mb_strtolower($ref)])) {
                throw new InvalidArgumentException("Bill {$ref} is listed twice.");
            }
            $refs[mb_strtolower($ref)] = true;

            $billDate = ! empty($row['bill_date']) ? Carbon::parse($row['bill_date'])->toDateString() : $opening['date'];
            if ($billDate > $opening['date']) {
                throw new InvalidArgumentException("Bill {$ref} is dated after the books start ({$opening['date']}); enter it as a normal bill instead.");
            }
            $dueDate = ! empty($row['due_date']) ? Carbon::parse($row['due_date'])->toDateString() : $billDate;
            if ($dueDate < $billDate) {
                throw new InvalidArgumentException("Bill {$ref} is due before its bill date.");
            }

            $clean[] = ['ref' => mb_substr($ref, 0, 100), 'bill_date' => $billDate, 'due_date' => $dueDate, 'amount' => $amount];
        }

        $total = round(array_sum(array_column($clean, 'amount')), 2);
        if ($clean !== [] && abs($total - $opening['amount']) >= 0.005) {
            throw new InvalidArgumentException('The old bills add up to ' . number_format($total, 2) . ' but the opening balance is ' . number_format($opening['amount'], 2) . '.');
        }

        return $clean;
    }

    /** An old bill number must not be one of the party's real bills on this ledger. */
    private function assertRefsFree(string $partyType, int $partyId, int $ledgerId, array $openingBillIds, array $rows): void
    {
        $taken = BillReference::query()
            ->where('party_type', $partyType)->where('party_id', $partyId)
            ->where('control_account_id', $ledgerId)
            ->whereNotIn('id', $openingBillIds)
            ->whereIn('ref_number', array_column($rows, 'ref'))
            ->value('ref_number');

        if ($taken !== null) {
            throw new InvalidArgumentException("Bill number {$taken} is already used by another bill of this party.");
        }
    }

    /** Move what settled the opening onto the new old bills, oldest due first. */
    private function repoint($settled, string $partyType, int $partyId, int $ledgerId, string $referenceType): void
    {
        $targets = BillReference::query()
            ->where('party_type', $partyType)->where('party_id', $partyId)
            ->where('control_account_id', $ledgerId)
            ->where('source_type', $referenceType)->where('source_id', $partyId)
            ->orderBy('due_date')->orderBy('bill_date')->orderBy('id')
            ->get();

        $open = $targets->mapWithKeys(fn (BillReference $b) => [$b->id => $this->bills->billOutstanding($b)])->all();

        foreach ($settled as $old) {
            $left = round((float) $old->amount, 2);

            foreach ($targets as $bill) {
                if (abs($left) < 0.005) {
                    break;
                }
                // Only a bill on the other side of this money can absorb it.
                if ($open[$bill->id] == 0.0 || ($open[$bill->id] > 0) === ($left > 0)) {
                    continue;
                }

                $take = min(abs($left), abs($open[$bill->id]));
                $signed = $left > 0 ? $take : -$take;
                $this->copy($old, $bill->id, BillAllocation::AGAINST_REF, $signed);
                $open[$bill->id] = round($open[$bill->id] + $signed, 2);
                $left = round($left - $signed, 2);
            }

            if (abs($left) >= 0.005) {
                $this->copy($old, null, BillAllocation::ON_ACCOUNT, $left);
            }
        }
    }

    private function copy(BillAllocation $old, ?int $billId, string $type, float $signed): void
    {
        BillAllocation::create([
            'tenant_id' => $old->tenant_id,
            'journal_entry_id' => $old->journal_entry_id,
            'bill_reference_id' => $billId,
            'party_type' => $old->party_type,
            'party_id' => $old->party_id,
            'control_account_id' => $old->control_account_id,
            'ref_type' => $type,
            'amount' => round($signed, 2),
            'allocated_on' => $old->allocated_on,
            'settlement_id' => $old->settlement_id,
        ]);
    }
}
