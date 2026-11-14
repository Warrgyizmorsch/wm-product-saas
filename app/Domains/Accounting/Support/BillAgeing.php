<?php

namespace App\Domains\Accounting\Support;

use App\Domains\Accounting\Services\BillWise\BillAllocationService;
use App\Domains\Accounting\Models\BillAllocation;
use Illuminate\Support\Carbon;

/**
 * Receivable / payable ageing from the bill-wise register, so it covers every
 * bill (invoices, vendor bills, opening dues, returns, vouchers) and each
 * party's total equals its ledger balance: open bills by age + money On
 * Account (receipts/payments not yet matched to a bill).
 *
 * Shape kept from the old document-based reports: parties[] with name,
 * buckets, on_account, total and documents[] (each with a doc object carrying
 * invoice_number / bill_number, due_date, days_overdue, bucket, balance).
 */
class BillAgeing
{
    public const BASIS_DUE = 'due';
    public const BASIS_BILL = 'bill';

    public function __construct(
        private readonly BillAllocationService $bills,
        private readonly PartyDirectory $parties,
    ) {
    }

    /**
     * @param 'receivable'|'payable' $ledger  which side; with $ledgerId only that one ledger
     * @return array{parties: list<array<string, mixed>>, buckets: array<string, float>, onAccount: float, advance: float, grandTotal: float}
     */
    public function build(string $ledger, Carbon $asOf, string $basis = self::BASIS_DUE, ?int $ledgerId = null, ?string $onlyPartyType = null): array
    {
        // Receivables read positive when owed to us, payables when we owe.
        $sign = $ledger === 'receivable' ? 1 : -1;
        $partyType = $ledger === 'receivable' ? 'customer' : 'vendor';
        $documentKey = $ledger === 'receivable' ? 'invoice' : 'bill';
        $numberField = $ledger === 'receivable' ? 'invoice_number' : 'bill_number';

        $emptyBuckets = ['not_due' => 0.0, '0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];
        $buckets = $emptyBuckets;
        $parties = [];
        $onAccountTotal = 0.0;
        $advanceTotal = 0.0;

        $party = function (string $type, int $id) use (&$parties, $emptyBuckets, $partyType) {
            $key = "{$type}:{$id}";
            $parties[$key] ??= [
                "{$partyType}_id" => $id,
                'party_type' => $type,
                'party_id' => $id,
                'name' => $this->parties->name($type, $id),
                'type_label' => PartyDirectory::label($type),
                'buckets' => $emptyBuckets,
                'on_account' => 0.0,
                'advance' => 0.0,
                'total' => 0.0,
                'documents' => [],
            ];

            return $key;
        };

        foreach ($this->bills->openBills($ledger, $asOf->toDateString(), null, null, $ledgerId) as $row) {
            $bill = $row->bill;
            if ($onlyPartyType && $bill->party_type !== $onlyPartyType) {
                continue;
            }
            $balance = round($sign * $row->outstanding, 2);
            $from = $basis === self::BASIS_BILL ? $bill->bill_date : ($bill->due_date ?? $bill->bill_date);
            $from = $from ? Carbon::parse($from) : null;
            $days = ($from && $from->lt($asOf)) ? (int) abs($asOf->diffInDays($from)) : 0;
            $bucket = $this->bucketFor($days);

            $key = $party($bill->party_type, (int) $bill->party_id);
            $parties[$key]['buckets'][$bucket] += $balance;
            $parties[$key]['total'] += $balance;
            $parties[$key]['documents'][] = [
                $documentKey => (object) [$numberField => $bill->ref_number, 'bill_date' => $bill->bill_date, 'source_type' => $bill->source_type, 'source_id' => $bill->source_id],
                'bill_date' => $bill->bill_date ? Carbon::parse($bill->bill_date) : null,
                'due_date' => $bill->due_date ? Carbon::parse($bill->due_date) : null,
                'days_overdue' => $days,
                'bucket' => $bucket,
                'balance' => $balance,
            ];
            $buckets[$bucket] += $balance;
        }

        // Money not matched to a bill: advances (opening credits, advance
        // payments) shown apart from plain On Account receipts/payments.
        foreach ([BillAllocation::ON_ACCOUNT => 'on_account', BillAllocation::ADVANCE => 'advance'] as $refType => $column) {
            foreach ($this->bills->onAccount($ledger, $asOf->toDateString(), $ledgerId, $refType) as $partyKey => $amount) {
                [$type, $id] = explode(':', $partyKey);
                if ($onlyPartyType && $type !== $onlyPartyType) {
                    continue;
                }
                $key = $party($type, (int) $id);
                $value = round($sign * $amount, 2);
                $parties[$key][$column] += $value;
                $parties[$key]['total'] += $value;
                $column === 'advance' ? $advanceTotal += $value : $onAccountTotal += $value;
            }
        }

        $parties = array_values($parties);
        foreach ($parties as &$row) {
            usort($row['documents'], fn ($a, $b) => $b['days_overdue'] <=> $a['days_overdue']);
        }
        unset($row);
        usort($parties, fn ($a, $b) => $b['total'] <=> $a['total']);

        return [
            'parties' => $parties,
            'buckets' => $buckets,
            'onAccount' => round($onAccountTotal, 2),
            'advance' => round($advanceTotal, 2),
            'grandTotal' => round(array_sum($buckets) + $onAccountTotal + $advanceTotal, 2),
        ];
    }


    private function bucketFor(int $days): string
    {
        return match (true) {
            $days <= 0 => 'not_due',
            $days <= 30 => '0_30',
            $days <= 60 => '31_60',
            $days <= 90 => '61_90',
            default => '90_plus',
        };
    }
}
