<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Purchase\Models\VendorBill;
use App\Http\Controllers\Controller;
use App\Services\Access\AccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ApAgingController extends Controller
{
    /**
     * Statuses that still carry an open payable balance. Draft bills aren't
     * posted yet, Paid/Cancelled have nothing outstanding.
     */
    private const OPEN_STATUSES = ['Posted', 'Partially Paid'];

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

        $bills = VendorBill::with('vendor')
            ->whereIn('status', self::OPEN_STATUSES)
            ->where('due_amount', '>', 0)
            ->orderBy('due_date')
            ->get();

        $buckets = ['not_due' => 0.0, '0_30' => 0.0, '31_60' => 0.0, '61_90' => 0.0, '90_plus' => 0.0];
        $vendors = [];

        foreach ($bills as $bill) {
            $vendorId = $bill->vendor_id;
            $vendorName = $bill->vendor?->name ?? 'Unknown Vendor';

            $dueDate = $bill->due_date ? Carbon::parse($bill->due_date) : null;
            $daysOverdue = ($dueDate && $dueDate->lt($asOf)) ? $asOf->diffInDays($dueDate) : 0;
            $bucket = $this->bucketFor($daysOverdue);

            $balance = (float) $bill->due_amount;

            $vendors[$vendorId] ??= [
                'name' => $vendorName,
                'buckets' => $buckets,
                'total' => 0.0,
                'bills' => [],
            ];

            $vendors[$vendorId]['buckets'][$bucket] += $balance;
            $vendors[$vendorId]['total'] += $balance;
            $vendors[$vendorId]['bills'][] = [
                'bill' => $bill,
                'due_date' => $dueDate,
                'days_overdue' => $daysOverdue,
                'bucket' => $bucket,
                'balance' => $balance,
            ];

            $buckets[$bucket] += $balance;
        }

        $grandTotal = array_sum($buckets);

        usort($vendors, fn ($a, $b) => $b['total'] <=> $a['total']);

        return view('modules.accounting.reports.ap-aging', [
            'asOf' => $asOf,
            'vendors' => $vendors,
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
