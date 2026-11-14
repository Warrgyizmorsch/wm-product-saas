<?php

namespace App\Domains\Accounting\Services\BillWise;

use App\Domains\Accounting\Models\BillAllocation;
use App\Domains\Accounting\Models\BillReference;
use App\Domains\Accounting\Models\BillSettlement;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\AccountingAuditLogService;
use App\Domains\Accounting\Services\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Adjust a party's unmatched money (On Account / Advance) against its open
 * bills after the fact — Tally's "bill-wise adjustment".
 *
 * On the same ledger nothing is posted: the unmatched line gets two more
 * allocations (piece -x, Agst Ref bill +x), so the line total and the ledger
 * balance stay exactly as they are. An advance sitting on another ledger
 * (Advance from Customers vs Receivable, Advance to Suppliers vs Payable) has
 * to move between ledgers, so that part posts a transfer journal.
 */
class BillSettlementService
{
    public const REFERENCE_TYPE = 'bill_settlement';

    public function __construct(
        private readonly BillAllocationService $bills,
        private readonly JournalService $journals,
        private readonly AccountingAuditLogService $auditLog,
    ) {
    }

    /**
     * Open bills of a party on every bill-wise ledger, oldest due first.
     *
     * @return Collection<int, object{bill: BillReference, outstanding: float, ledger_id: int}>
     */
    public function openBills(string $partyType, int $partyId): Collection
    {
        $totals = BillAllocation::query()
            ->where('party_type', $partyType)->where('party_id', $partyId)
            ->whereIn('control_account_id', $this->ledgerIds())
            ->whereNotNull('bill_reference_id')
            ->groupBy('bill_reference_id')
            ->selectRaw('bill_reference_id, SUM(amount) as outstanding')
            ->havingRaw('ABS(SUM(amount)) >= 0.005')
            ->pluck('outstanding', 'bill_reference_id');

        return BillReference::query()->whereIn('id', $totals->keys())->get()
            ->map(fn (BillReference $bill) => (object) [
                'bill' => $bill,
                'outstanding' => round((float) $totals[$bill->id], 2),
                'ledger_id' => (int) $bill->control_account_id,
            ])
            ->sortBy(fn ($row) => [$row->bill->due_date?->toDateString() ?? '', $row->bill->bill_date?->toDateString() ?? '', $row->bill->id])
            ->values();
    }

    /**
     * Money of a party not matched to any bill, per journal line, oldest
     * first. On each ledger the opposite-side pieces are netted off first
     * (an advance of 10,000 partly refunded by 4,000 shows as 6,000).
     *
     * @return Collection<int, object{key: string, entry: JournalEntry, ledger_id: int, ref_type: string, amount: float, date: string}>
     */
    public function unmatched(string $partyType, int $partyId): Collection
    {
        $rows = BillAllocation::query()
            ->where('party_type', $partyType)->where('party_id', $partyId)
            ->whereIn('control_account_id', $this->ledgerIds())
            ->whereNull('bill_reference_id')
            ->groupBy('journal_entry_id', 'control_account_id', 'ref_type')
            ->selectRaw('journal_entry_id, control_account_id, ref_type, SUM(amount) as balance, MIN(allocated_on) as first_on')
            ->havingRaw('ABS(SUM(amount)) >= 0.005')
            ->get();

        $entries = JournalEntry::query()->with('journal:id,journal_number,journal_date,memo')
            ->whereIn('id', $rows->pluck('journal_entry_id'))->get()->keyBy('id');

        $pieces = collect();

        foreach ($rows->groupBy('control_account_id') as $ledgerId => $ledgerRows) {
            $ledgerRows = $ledgerRows
                ->sortBy(fn ($r) => [Carbon::parse($entries[$r->journal_entry_id]->journal->journal_date ?? $r->first_on)->toDateString(), $r->journal_entry_id])
                ->values();
            $net = round((float) $ledgerRows->sum('balance'), 2);
            if (abs($net) < 0.005) {
                continue;
            }

            // Opposite-side pieces cancel the oldest dominant-side ones.
            $offset = round(abs((float) $ledgerRows->filter(fn ($r) => ($r->balance > 0) !== ($net > 0))->sum('balance')), 2);

            foreach ($ledgerRows->filter(fn ($r) => ($r->balance > 0) === ($net > 0)) as $row) {
                $magnitude = abs((float) $row->balance);
                $used = min($offset, $magnitude);
                $offset = round($offset - $used, 2);
                $left = round($magnitude - $used, 2);
                if ($left < 0.005) {
                    continue;
                }

                $entry = $entries[$row->journal_entry_id];
                $pieces->push((object) [
                    'key' => "{$row->journal_entry_id}:{$row->ref_type}",
                    'entry' => $entry,
                    'ledger_id' => (int) $ledgerId,
                    'ref_type' => $row->ref_type,
                    'amount' => $net > 0 ? $left : -$left,
                    'date' => Carbon::parse($entry->journal->journal_date ?? $row->first_on)->toDateString(),
                ]);
            }
        }

        return $pieces->sortBy(fn ($p) => [$p->date, $p->entry->id])->values();
    }

    /**
     * Oldest-first matching: each piece against the oldest opposite-side bills.
     *
     * @return array{bills: array<int, float>, pieces: array<string, float>}
     */
    public function suggest(string $partyType, int $partyId): array
    {
        $bills = $this->openBills($partyType, $partyId)->map(fn ($b) => clone $b)->all();
        $result = ['bills' => [], 'pieces' => []];

        foreach ($this->unmatched($partyType, $partyId) as $piece) {
            $left = abs($piece->amount);

            foreach ($bills as $bill) {
                if ($left < 0.005) {
                    break;
                }
                if (($bill->outstanding > 0) === ($piece->amount > 0) || abs($bill->outstanding) < 0.005) {
                    continue;
                }

                $take = round(min($left, abs($bill->outstanding)), 2);
                $result['bills'][$bill->bill->id] = round(($result['bills'][$bill->bill->id] ?? 0) + $take, 2);
                $result['pieces'][$piece->key] = round(($result['pieces'][$piece->key] ?? 0) + $take, 2);
                $bill->outstanding = round($bill->outstanding + ($bill->outstanding > 0 ? -$take : $take), 2);
                $left = round($left - $take, 2);
            }
        }

        return $result;
    }

    /**
     * Match the chosen pieces to the chosen bills (paired oldest first).
     *
     * @param array<int, float>    $billAmounts  bill id => amount to settle
     * @param array<string, float> $pieceAmounts piece key ("entryId:refType") => amount to use
     */
    public function settle(string $partyType, int $partyId, array $billAmounts, array $pieceAmounts, ?string $note = null, $date = null, ?int $userId = null): BillSettlement
    {
        $billAmounts = array_filter(array_map(fn ($v) => round((float) $v, 2), $billAmounts), fn ($v) => $v > 0);
        $pieceAmounts = array_filter(array_map(fn ($v) => round((float) $v, 2), $pieceAmounts), fn ($v) => $v > 0);

        if ($billAmounts === [] || $pieceAmounts === []) {
            throw new InvalidArgumentException('Pick at least one bill and the money to adjust against it.');
        }
        if (abs(array_sum($billAmounts) - array_sum($pieceAmounts)) >= 0.005) {
            throw new InvalidArgumentException('The bills picked (' . number_format(array_sum($billAmounts), 2) . ') and the money used (' . number_format(array_sum($pieceAmounts), 2) . ') must be equal.');
        }

        return DB::transaction(function () use ($partyType, $partyId, $billAmounts, $pieceAmounts, $note, $date, $userId) {
            $openBills = $this->openBills($partyType, $partyId)->keyBy(fn ($b) => $b->bill->id);
            $pieces = $this->unmatched($partyType, $partyId)->keyBy('key');
            $settledOn = Carbon::parse($date ?? now())->toDateString();

            // Bills in due order, pieces oldest first, each checked against what is open.
            $billQueue = [];
            foreach ($openBills as $id => $row) {
                if (isset($billAmounts[$id])) {
                    if ($billAmounts[$id] - abs($row->outstanding) > 0.005) {
                        throw new InvalidArgumentException("Bill {$row->bill->ref_number} has only " . number_format(abs($row->outstanding), 2) . ' open.');
                    }
                    $billQueue[] = ['row' => $row, 'left' => $billAmounts[$id]];
                    unset($billAmounts[$id]);
                }
            }
            $pieceQueue = [];
            foreach ($pieces as $key => $piece) {
                if (isset($pieceAmounts[$key])) {
                    if ($pieceAmounts[$key] - abs($piece->amount) > 0.005) {
                        throw new InvalidArgumentException('Only ' . number_format(abs($piece->amount), 2) . " is unadjusted on {$piece->entry->journal->journal_number}.");
                    }
                    $pieceQueue[] = ['piece' => $piece, 'left' => $pieceAmounts[$key]];
                    unset($pieceAmounts[$key]);
                }
            }
            if ($billAmounts !== [] || $pieceAmounts !== []) {
                throw new InvalidArgumentException('Some of the picked bills or amounts are no longer open. Reload the page and try again.');
            }

            $billSign = $billQueue[0]['row']->outstanding > 0;
            foreach ($billQueue as $b) {
                if (($b['row']->outstanding > 0) !== $billSign) {
                    throw new InvalidArgumentException('Pick bills on one side only (all receivable or all payable).');
                }
            }
            foreach ($pieceQueue as $p) {
                if (($p['piece']->amount > 0) === $billSign) {
                    throw new InvalidArgumentException('That money is on the same side as the bills, so it cannot settle them.');
                }
            }

            $settlement = BillSettlement::create([
                'party_type' => $partyType,
                'party_id' => $partyId,
                'control_account_id' => $billQueue[0]['row']->ledger_id,
                'settled_on' => $settledOn,
                'amount' => round(array_sum(array_column($billQueue, 'left')), 2),
                'note' => $note ? mb_substr($note, 0, 255) : null,
                'created_by' => $userId,
            ]);

            $transfer = [];
            $bi = 0;
            foreach ($pieceQueue as $p) {
                $piece = $p['piece'];
                $left = $p['left'];

                while ($left >= 0.005 && isset($billQueue[$bi])) {
                    $take = round(min($left, $billQueue[$bi]['left']), 2);
                    $bill = $billQueue[$bi]['row'];

                    if ($piece->ledger_id === $bill->ledger_id) {
                        $this->moveOnLine($settlement, $piece, $bill->bill, $take, $settledOn);
                    } else {
                        $transfer[] = [$piece, $bill, $take];
                    }

                    $left = round($left - $take, 2);
                    $billQueue[$bi]['left'] = round($billQueue[$bi]['left'] - $take, 2);
                    if ($billQueue[$bi]['left'] < 0.005) {
                        $bi++;
                    }
                }
            }

            if ($transfer !== []) {
                $journal = $this->postTransfer($settlement, $transfer, $userId);
                $settlement->update(['journal_id' => $journal->id]);
            }

            $this->auditLog->record($settlement, 'bill_settlement.created', 'Bills adjusted against On Account / Advance', [
                'party' => "{$partyType}:{$partyId}",
                'amount' => (float) $settlement->amount,
                'journal_id' => $settlement->journal_id,
            ]);

            return $settlement->fresh();
        });
    }

    /** Undo an adjustment: the bills reopen and the money is unadjusted again. */
    public function undo(BillSettlement $settlement, ?int $userId = null): void
    {
        DB::transaction(function () use ($settlement, $userId) {
            if ($settlement->journal_id) {
                $journal = Journal::find($settlement->journal_id);
                if ($journal && $journal->status === Journal::STATUS_POSTED) {
                    $this->journals->reverse($journal->id, 'Bill adjustment undone', $userId);
                }
            }

            BillAllocation::where('settlement_id', $settlement->id)->delete();

            $this->auditLog->record($settlement, 'bill_settlement.undone', 'Bill adjustment undone', [
                'party' => "{$settlement->party_type}:{$settlement->party_id}",
                'amount' => (float) $settlement->amount,
            ]);

            $settlement->delete();
        });
    }

    /** Same ledger: re-explain part of the unmatched line as settling the bill. */
    private function moveOnLine(BillSettlement $settlement, object $piece, BillReference $bill, float $amount, string $date): void
    {
        $sign = $piece->amount > 0 ? 1 : -1;

        foreach ([[$piece->ref_type, -$sign * $amount, null], [BillAllocation::AGAINST_REF, $sign * $amount, $bill->id]] as [$type, $signed, $billId]) {
            BillAllocation::create([
                'tenant_id' => $piece->entry->tenant_id,
                'journal_entry_id' => $piece->entry->id,
                'bill_reference_id' => $billId,
                'party_type' => $settlement->party_type,
                'party_id' => $settlement->party_id,
                'control_account_id' => $piece->ledger_id,
                'ref_type' => $type,
                'amount' => round($signed, 2),
                'allocated_on' => $date,
                'settlement_id' => $settlement->id,
            ]);
        }
    }

    /**
     * Another ledger: Dr / Cr the advance ledger to clear the piece and the
     * opposite on the bill's ledger, Agst Ref the bill.
     *
     * @param list<array{0: object, 1: object, 2: float}> $transfer
     */
    private function postTransfer(BillSettlement $settlement, array $transfer, ?int $userId): Journal
    {
        $lines = [];
        $hints = [];
        $names = ChartOfAccount::query()->whereIn('id', collect($transfer)->flatMap(fn ($t) => [$t[0]->ledger_id, $t[1]->ledger_id]))->pluck('name', 'id');

        foreach ($transfer as [$piece, $bill, $amount]) {
            $pieceDebit = $piece->amount < 0; // a credit advance is cleared by a debit
            $ref = $bill->bill->ref_number;

            $hints[] = ['ref_type' => $piece->ref_type, 'line_index' => count($lines), 'amount' => $amount];
            $lines[] = [
                'chart_of_account_id' => $piece->ledger_id,
                'debit' => $pieceDebit ? $amount : 0,
                'credit' => $pieceDebit ? 0 : $amount,
                'description' => "{$names[$piece->ledger_id]} adjusted against {$ref}",
                'party_type' => $settlement->party_type,
                'party_id' => $settlement->party_id,
            ];

            $hints[] = ['ref_type' => BillAllocation::AGAINST_REF, 'line_index' => count($lines), 'bill_reference_id' => $bill->bill->id, 'amount' => $amount];
            $lines[] = [
                'chart_of_account_id' => $bill->ledger_id,
                'debit' => $pieceDebit ? 0 : $amount,
                'credit' => $pieceDebit ? $amount : 0,
                'description' => "Agst {$ref} from {$names[$piece->ledger_id]}",
                'party_type' => $settlement->party_type,
                'party_id' => $settlement->party_id,
            ];
        }

        return $this->journals->post($lines, [
            'journal_date' => $settlement->settled_on,
            'source' => Journal::SOURCE_MANUAL,
            'reference_type' => self::REFERENCE_TYPE,
            'reference_id' => $settlement->id,
            'memo' => 'Advance adjusted against bills' . ($settlement->note ? " — {$settlement->note}" : ''),
            'posted_by' => $userId,
            'bill_allocations' => $hints,
        ]);
    }

    /** @return list<int> */
    private function ledgerIds(): array
    {
        return array_keys($this->bills->controlAccounts((int) tenant_id()));
    }
}
