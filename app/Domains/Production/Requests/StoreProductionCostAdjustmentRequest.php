<?php

namespace App\Domains\Production\Requests;

use App\Domains\Production\Models\ProductionOrder;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductionCostAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $orderId = $this->route('order') ?? $this->input('production_order_id');
        if (!$orderId) {
            return false;
        }

        $order = ProductionOrder::find($orderId);
        if (!$order) {
            return false;
        }

        $user = $this->user();
        if (!$user || $user->tenant_id !== $order->tenant_id) {
            return false;
        }

        if ($order->isCompleted() || $order->isClosed() || $order->isCancelled()) {
            return false;
        }

        return $user->role === 'admin'
            || $user->hasProductionPermission('production.cost_adjustment.create', $order->tenant_id)
            || $user->hasProductionPermission('production.order.update', $order->tenant_id);
    }

    public function rules(): array
    {
        return [
            'cost_component'  => 'required|string|in:material,labor,machine,overhead,other',
            'category'        => 'required|string|max:100',
            'description'     => 'required|string|max:255',
            'amount'          => 'required|numeric|gt:0',
            'adjustment_date' => 'required|date|before_or_equal:today',
            'attachment'      => 'nullable|file|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,zip|max:10240',
            'notes'           => 'nullable|string|max:500',
        ];
    }
}
