<?php

namespace App\Domains\Sales\Services;

use App\Domains\Party\Contracts\PartyHistoryProvider;
use App\Domains\Sales\Models\CustomerPayment;
use App\Domains\Sales\Models\Invoice;
use App\Domains\Sales\Models\SalesOrder;
use App\Domains\Sales\Models\SalesReturn;
use Illuminate\Support\Collection;

/** Sales orders, invoices, receipts and returns of a customer. */
class CustomerHistoryProvider implements PartyHistoryProvider
{
    public function partyType(): string
    {
        return 'customer';
    }

    public function entries(int $partyId): Collection
    {
        $rows = collect();

        foreach (SalesOrder::where('customer_id', $partyId)->get() as $o) {
            $rows->push($this->row('sales_order', 'Sales Order', $o->sales_order_number, $o->total_amount, $o->status, $o->order_date ?? $o->created_at, route('sales.orders.show', $o->id), 'sales.orders.view'));
        }
        foreach (Invoice::where('customer_id', $partyId)->get() as $i) {
            $rows->push($this->row('invoice', 'Invoice', $i->invoice_number, $i->total_amount, $i->status, $i->invoice_date ?? $i->created_at, route('sales.invoices.show', $i->id), 'sales.invoices.view'));
        }
        foreach (CustomerPayment::where('customer_id', $partyId)->get() as $p) {
            $rows->push($this->row('payment', 'Payment received', $p->payment_number, $p->amount, $p->status, $p->payment_date ?? $p->created_at, route('sales.payments.show', $p->id), 'sales.payments.view'));
        }
        foreach (SalesReturn::where('customer_id', $partyId)->get() as $r) {
            $rows->push($this->row('sales_return', 'Sales Return', $r->return_number, $r->total_amount, $r->status, $r->return_date ?? $r->created_at, route('sales.returns.show', $r->id), 'sales.returns.view'));
        }

        return $rows;
    }

    private function row($type, $label, $number, $amount, $status, $date, $url, $permission): array
    {
        return compact('type', 'number', 'amount', 'status', 'date', 'url', 'permission') + ['type_label' => $label, 'title' => null];
    }
}
