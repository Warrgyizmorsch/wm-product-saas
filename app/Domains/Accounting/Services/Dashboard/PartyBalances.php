<?php

namespace App\Domains\Accounting\Services\Dashboard;

use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Sales\Models\Invoice;
use Illuminate\Support\Carbon;

/**
 * Outstanding customer invoices and vendor bills: aging buckets, largest
 * parties, and how much falls due within a horizon (for the cash forecast).
 */
class PartyBalances
{
    /** Same open statuses as ArAgingController / ApAgingController. */
    private const OPEN_INVOICE_STATUSES = ['Sent', 'Posted', 'Partially Paid'];
    private const OPEN_BILL_STATUSES = ['Unpaid', 'Partially Paid'];

    public const BUCKETS = [
        'not_due' => 'Not due',
        '0_30' => '1–30 days',
        '31_60' => '31–60 days',
        '61_90' => '61–90 days',
        '90_plus' => '90+ days',
    ];

    public function receivables(Carbon $today, int $horizonDays = 30): array
    {
        $invoices = Invoice::query()
            ->with('customer')
            ->whereIn('status', self::OPEN_INVOICE_STATUSES)
            ->where('balance_due', '>', 0)
            ->get();

        return $this->summarize($invoices->map(fn (Invoice $invoice) => [
            'party' => $invoice->customer?->name ?? 'Unknown Customer',
            'due_date' => $invoice->due_date,
            'amount' => (float) $invoice->balance_due,
        ]), $today, $horizonDays);
    }

    public function payables(Carbon $today, int $horizonDays = 30): array
    {
        $bills = VendorBill::query()
            ->with('vendor')
            ->whereIn('status', self::OPEN_BILL_STATUSES)
            ->where('due_amount', '>', 0)
            ->get();

        return $this->summarize($bills->map(fn (VendorBill $bill) => [
            'party' => $bill->vendor?->name ?? 'Unknown Vendor',
            'due_date' => $bill->due_date,
            'amount' => (float) $bill->due_amount,
        ]), $today, $horizonDays);
    }

    /**
     * A document with no due date is treated as due now.
     *
     * @param iterable<array{party: string, due_date: mixed, amount: float}> $documents
     * @return array{total: float, overdue: float, count: int, buckets: array<string, float>, top: list<array{name: string, amount: float}>, due_within_horizon: float}
     */
    public function summarize(iterable $documents, Carbon $today, int $horizonDays = 30, int $top = 5): array
    {
        $today = $today->copy()->startOfDay();
        $horizon = $today->copy()->addDays($horizonDays);
        $buckets = array_fill_keys(array_keys(self::BUCKETS), 0.0);
        $parties = [];
        $count = 0;
        $dueWithinHorizon = 0.0;

        foreach ($documents as $document) {
            $amount = (float) $document['amount'];
            $dueDate = $document['due_date'] ? Carbon::parse($document['due_date'])->startOfDay() : null;
            $daysOverdue = $dueDate !== null && $dueDate->lt($today) ? (int) round(abs($dueDate->diffInDays($today))) : 0;

            $buckets[$this->bucketFor($dueDate === null ? 0 : $daysOverdue, $dueDate !== null && $dueDate->lt($today))] += $amount;
            $parties[$document['party']] = ($parties[$document['party']] ?? 0.0) + $amount;

            if ($dueDate === null || $dueDate->lte($horizon)) {
                $dueWithinHorizon += $amount;
            }

            $count++;
        }

        arsort($parties);
        $total = array_sum($buckets);

        return [
            'total' => round($total, 2),
            'overdue' => round($total - $buckets['not_due'], 2),
            'count' => $count,
            'buckets' => array_map(fn (float $value) => round($value, 2), $buckets),
            'top' => collect($parties)
                ->take($top)
                ->map(fn (float $amount, string $name) => ['name' => $name, 'amount' => round($amount, 2)])
                ->values()
                ->all(),
            'due_within_horizon' => round($dueWithinHorizon, 2),
        ];
    }

    private function bucketFor(int $daysOverdue, bool $isOverdue): string
    {
        return match (true) {
            ! $isOverdue => 'not_due',
            $daysOverdue <= 30 => '0_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => '90_plus',
        };
    }
}
