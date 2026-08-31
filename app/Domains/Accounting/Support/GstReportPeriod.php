<?php

namespace App\Domains\Accounting\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Shared plumbing for the GST reports (GST Summary, GSTR-1, GSTR-3B): a
 * from/to date range (GST returns are calendar-month based, unlike the
 * accounting-period-based reports elsewhere in this module) and deriving a
 * Sales/Purchase Return's tax split from total_refund_amount - total_amount,
 * since SalesReturn/PurchaseReturn don't store a CGST/SGST/IGST breakdown —
 * mirrors the same estimation PostSalesReturnJournal/PostPurchaseReturnJournal
 * already use when actually posting the credit/debit note journal.
 */
class GstReportPeriod
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public static function resolve(Request $request): array
    {
        $from = $request->filled('from') ? Carbon::parse($request->string('from')) : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->string('to')) : now()->endOfMonth();

        return [$from->startOfDay(), $to->endOfDay()];
    }

    /**
     * @return array{taxable: float, cgst: float, sgst: float, igst: float, total_tax: float}
     */
    public static function splitReturnTax(object $return, ?object $sourceDocument): array
    {
        $taxable = (float) $return->total_amount;
        $totalTax = max(0.0, (float) $return->total_refund_amount - $taxable);
        $isInterState = $sourceDocument && (float) ($sourceDocument->igst_amount ?? 0) > 0;

        $igst = $isInterState ? $totalTax : 0.0;
        $cgst = $isInterState ? 0.0 : round($totalTax / 2, 2);
        $sgst = $isInterState ? 0.0 : round($totalTax - $cgst, 2);

        return [
            'taxable' => $taxable,
            'cgst' => $cgst,
            'sgst' => $sgst,
            'igst' => $igst,
            'total_tax' => $totalTax,
        ];
    }
}
