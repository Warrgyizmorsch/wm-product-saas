<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Party\Contracts\PartyHistoryProvider;
use App\Domains\Purchase\Models\PurchaseAdvancePayment;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseRfqVendor;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Models\VendorPayment;
use Illuminate\Support\Collection;

/** RFQs, purchase orders, bills, payments and advances of a vendor. */
class VendorHistoryProvider implements PartyHistoryProvider
{
    public function partyType(): string
    {
        return 'vendor';
    }

    public function entries(int $partyId): Collection
    {
        $rows = collect();

        foreach (PurchaseRfqVendor::where('vendor_id', $partyId)->with('rfq')->get() as $rv) {
            if (! $rv->rfq) {
                continue;
            }
            $rows->push($this->row('rfq', 'RFQ', $rv->rfq->rfq_number, null, $rv->status, $rv->rfq->rfq_date ?? $rv->created_at, route('purchase.rfqs.show', $rv->purchase_rfq_id), 'purchase.rfqs.view'));
        }
        foreach (PurchaseOrder::where('vendor_id', $partyId)->get() as $po) {
            $rows->push($this->row('purchase_order', 'Purchase Order', $po->purchase_order_number, $po->grand_total, $po->status, $po->date ?? $po->created_at, route('purchase.orders.show', $po->id), 'purchase.orders.view'));
        }
        foreach (VendorBill::where('vendor_id', $partyId)->get() as $b) {
            $rows->push($this->row('bill', 'Vendor Bill', $b->bill_number, $b->grand_total, $b->status, $b->bill_date ?? $b->created_at, route('purchase.bills.show', $b->id), 'purchase.bills.view'));
        }
        foreach (VendorPayment::where('vendor_id', $partyId)->get() as $p) {
            $rows->push($this->row('payment', 'Payment made', $p->payment_number, $p->amount, $p->status, $p->payment_date ?? $p->created_at, route('purchase.payments.show', $p->id), 'purchase.payments.view'));
        }
        foreach (PurchaseAdvancePayment::where('vendor_id', $partyId)->get() as $a) {
            $rows->push($this->row('advance', 'Advance payment', $a->payment_number, $a->amount, $a->status, $a->payment_date ?? $a->created_at, route('purchase.advances.show', $a->id), 'purchase.advances.view'));
        }

        return $rows;
    }

    private function row($type, $label, $number, $amount, $status, $date, $url, $permission): array
    {
        return compact('type', 'number', 'amount', 'status', 'date', 'url', 'permission') + ['type_label' => $label, 'title' => null];
    }
}
