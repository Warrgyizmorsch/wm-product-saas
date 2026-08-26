<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Sales\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ArAgingController extends Controller
{
    /**
     * Statuses that still carry an open receivable balance. Draft invoices
     * aren't posted yet, Paid/Cancelled have nothing outstanding. 'Sent' is
     * the normal state for an invoice that has gone out to the customer and
     * still carries a real GL receivable (posting to the ledger happens
     * automatically at creation, independent of this workflow status).
     */
    private const OPEN_STATUSES = ['Sent', 'Posted', 'Partially Paid'];

    public function __construct(
        private readonly AccessService $access,
    ) {
    }

    public function index(Request $request): View
    {
        abort_unless($this->access->allows(auth()->user(), 'accounting.reports.view', [
            'tenant_id' => auth()->user()->tenant_id,
        ]), 403);

        $asOf = $request->filled('as_of') ? Carbon::parse($request->string('as_of')) : Carbon::today();

        $invoices = Invoice::with('customer')
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('balance_due', '>', 0)
            ->orderBy('due_date')
            ->get();

        $buckets = ['not_due' => 0.0, '0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];
        $customers = [];

        foreach ($invoices as $invoice) {
            $customerId = $invoice->customer_id;
            $customerName = $invoice->customer?->name ?? 'Unknown Customer';

            $dueDate = $invoice->due_date ? Carbon::parse($invoice->due_date) : null;
            $daysOverdue = ($dueDate && $dueDate->lt($asOf)) ? $asOf->diffInDays($dueDate) : 0;
            $bucket = $this->bucketFor($daysOverdue);

            $balance = (float) $invoice->balance_due;

            $customers[$customerId] ??= [
                'name' => $customerName,
                'buckets' => $buckets,
                'total' => 0.0,
                'invoices' => [],
            ];

            $customers[$customerId]['buckets'][$bucket] += $balance;
            $customers[$customerId]['total'] += $balance;
            $customers[$customerId]['invoices'][] = [
                'invoice' => $invoice,
                'due_date' => $dueDate,
                'days_overdue' => max(0, $daysOverdue),
                'bucket' => $bucket,
                'balance' => $balance,
            ];

            $buckets[$bucket] += $balance;
        }

        $grandTotal = array_sum($buckets);

        usort($customers, fn ($a, $b) => $b['total'] <=> $a['total']);

        return view('modules.accounting.reports.ar-aging', [
            'asOf' => $asOf,
            'customers' => $customers,
            'buckets' => $buckets,
            'grandTotal' => $grandTotal,
        ]);
    }

    private function bucketFor(int $daysOverdue): string
    {
        return match (true) {
            $daysOverdue <= 0 => 'not_due',
            $daysOverdue <= 30 => '0_30',
            $daysOverdue <= 60 => '31_60',
            $daysOverdue <= 90 => '61_90',
            default => '90_plus',
        };
    }
}
