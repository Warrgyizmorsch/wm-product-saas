<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Repositories\PurchaseOrderRepository;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Models\GstConfiguration;
use App\Models\PartyBranch;
use App\Services\TaxIdentityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PurchaseOrderService
{
    public function __construct(
        protected PurchaseOrderRepository $orderRepo
    ) {}

    public function storeOrder(array $validated, int $tenantId): PurchaseOrder
    {
        $this->assertSingleDeliveryTarget($validated);

        return DB::transaction(function () use ($validated, $tenantId) {
            $poNumber = $this->orderRepo->getNextPoNumber($tenantId);

            $validated = $this->applyBranchAndGstSnapshots($validated, $tenantId);

            $calculatedSubtotal = 0;
            $calculatedItemDiscount = 0;
            $calculatedItemTax = 0;

            foreach ($validated['items'] as $item) {
                $qty = (float)$item['quantity'];
                $price = convert_to_base((float)($item['unit_price'] ?? $item['rate'] ?? 0));
                $disc = convert_to_base((float)($item['discount'] ?? $item['discount_amount'] ?? 0));
                $taxRate = (float)($item['tax_rate'] ?? $item['tax_percent'] ?? 0);

                $lineSubtotal = $qty * $price;
                $calculatedSubtotal += $lineSubtotal;
                $calculatedItemDiscount += $disc;

                $lineTaxable = max(0, $lineSubtotal - $disc);
                $lineTax = $lineTaxable * ($taxRate / 100);
                $calculatedItemTax += $lineTax;
            }

            $discountType = $validated['discount_type'] ?? 'without_discount';
            $taxType = $validated['tax_type'] ?? 'order_wise_tax';
            $gstType = $validated['gst_type'] ?? null;

            if (empty($gstType) && $taxType !== 'without_tax' && !empty($validated['vendor_id'])) {
                $gstType = $this->resolveGstType(
                    (int) $validated['vendor_id'],
                    $validated['company_id'] ?? null,
                    $tenantId,
                    $validated['branch_id'] ?? null,
                    $validated['supplier_gstin'] ?? null,
                    $validated['supplier_state_code'] ?? null
                );
            }
            if (empty($gstType)) {
                $gstType = 'cgst_sgst';
            }

            $subtotal = $calculatedSubtotal;

            if ($discountType === 'order_wise') {
                $discountAmount = convert_to_base((float)($validated['discount_amount'] ?? 0));
            } elseif ($discountType === 'item_wise') {
                $discountAmount = $calculatedItemDiscount;
            } else {
                $discountAmount = 0;
            }

            $grossTotal = max(0, $subtotal - $discountAmount);

            if ($taxType === 'order_wise_tax') {
                $taxAmount = (float)($validated['tax_amount'] ?? 0);
            } elseif ($taxType === 'item_wise_tax') {
                $taxAmount = $calculatedItemTax;
            } else {
                $taxAmount = 0;
            }

            $cgstAmount = isset($validated['cgst_amount']) ? (float)$validated['cgst_amount'] : 0;
            $sgstAmount = isset($validated['sgst_amount']) ? (float)$validated['sgst_amount'] : 0;
            $igstAmount = isset($validated['igst_amount']) ? (float)$validated['igst_amount'] : 0;

            if ($taxType === 'without_tax') {
                $taxAmount = 0;
                $cgstAmount = 0;
                $sgstAmount = 0;
                $igstAmount = 0;
            } elseif ($taxAmount > 0 && ($cgstAmount + $sgstAmount + $igstAmount) <= 0) {
                if ($gstType === 'igst') {
                    $igstAmount = $taxAmount;
                } else {
                    $cgstAmount = round($taxAmount / 2, 4);
                    $sgstAmount = round($taxAmount - $cgstAmount, 4);
                }
            }

            $freightAmt = convert_to_base((float)($validated['freight_amount'] ?? 0));
            $isFreightIncluded = in_array($validated['freight_terms'] ?? 'to_pay', ['to_be_billed', 'prepaid']);
            $grandTotal = $grossTotal + $taxAmount + ($isFreightIncluded ? $freightAmt : 0);

            $po = $this->orderRepo->create([
                'tenant_id' => $tenantId,
                'company_id' => $validated['company_id'] ?? current_company_id() ?? auth()->user()?->company_id,
                'branch_id' => $validated['branch_id'] ?? current_branch_id() ?? auth()->user()?->branch_id,
                'purchase_order_number' => $poNumber,
                'date' => $validated['date'] ?? $validated['po_date'] ?? now()->toDateString(),
                'delivery_date' => $validated['delivery_date'] ?? $validated['expected_delivery_date'] ?? null,
                'vendor_id' => $validated['vendor_id'],
                'vendor_branch_id' => $validated['vendor_branch_id'] ?? null,
                'delivery_branch_id' => $validated['delivery_branch_id'] ?? null,
                'delivery_party_branch_id' => $validated['delivery_party_branch_id'] ?? null,
                'site_contact_id' => $validated['site_contact_id'] ?? null,
                'buyer_gstin' => $validated['buyer_gstin'] ?? null,
                'buyer_state_code' => $validated['buyer_state_code'] ?? null,
                'supplier_gstin' => $validated['supplier_gstin'] ?? null,
                'supplier_state_code' => $validated['supplier_state_code'] ?? null,
                'location' => $validated['location'] ?? null,
                'reference' => $validated['reference'] ?? null,
                'supplier_quotation_number' => $validated['supplier_quotation_number'] ?? null,
                'purchase_requisition_id' => $validated['purchase_requisition_id'] ?? null,
                'status' => 'Draft',
                'discount_type' => $discountType,
                'tax_type' => $taxType,
                'gst_type' => $gstType,
                'freight_terms' => $validated['freight_terms'] ?? 'to_pay',
                'freight_amount' => $freightAmt,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'cgst_amount' => $cgstAmount,
                'sgst_amount' => $sgstAmount,
                'igst_amount' => $igstAmount,
                'tax_amount' => $taxAmount,
                'grand_total' => $grandTotal,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id() ?: 1,
            ]);

            foreach ($validated['items'] as $item) {
                $qty = (float)$item['quantity'];
                $price = convert_to_base((float)($item['unit_price'] ?? $item['rate'] ?? 0));
                $disc = convert_to_base((float)($item['discount'] ?? $item['discount_amount'] ?? 0));
                $lineSubtotal = ($qty * $price) - $disc;

                if ($taxType === 'item_wise_tax') {
                    $taxRate = (float)($item['tax_rate'] ?? $item['tax_percent'] ?? 0);
                    $lineTax = $lineSubtotal * ($taxRate / 100);
                    $cgstPct = ($gstType === 'cgst_sgst') ? round($taxRate / 2, 4) : 0;
                    $sgstPct = ($gstType === 'cgst_sgst') ? round($taxRate / 2, 4) : 0;
                    $igstPct = ($gstType === 'igst') ? $taxRate : 0;
                    $cgstAmt = ($gstType === 'cgst_sgst') ? round($lineTax / 2, 4) : 0;
                    $sgstAmt = ($gstType === 'cgst_sgst') ? round($lineTax / 2, 4) : 0;
                    $igstAmt = ($gstType === 'igst') ? $lineTax : 0;
                } else {
                    $taxRate = 0;
                    $lineTax = 0;
                    $cgstPct = 0;
                    $sgstPct = 0;
                    $igstPct = 0;
                    $cgstAmt = 0;
                    $sgstAmt = 0;
                    $igstAmt = 0;
                }

                PurchaseOrderItem::create([
                    'tenant_id' => $tenantId,
                    'company_id' => $po->company_id,
                    'branch_id' => $po->branch_id,
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'] ?? null,
                    'description' => $item['description'] ?? null,
                    'line_type' => $item['line_type'] ?? PurchaseOrderItem::LINE_TYPE_STOCK,
                    'chart_of_account_id' => $item['chart_of_account_id'] ?? null,
                    'asset_category_id' => $item['asset_category_id'] ?? null,
                    'warehouse_id' => $item['warehouse_id'] ?? null,
                    'quantity' => $qty,
                    'rate' => $price,
                    'amount' => $lineSubtotal,
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'discount_amount' => $disc,
                    'tax_percent' => $taxRate,
                    'cgst_percent' => $cgstPct,
                    'sgst_percent' => $sgstPct,
                    'igst_percent' => $igstPct,
                    'cgst_amount' => $cgstAmt,
                    'sgst_amount' => $sgstAmt,
                    'igst_amount' => $igstAmt,
                    'tax_amount' => $lineTax,
                    'total_amount' => $lineSubtotal + $lineTax,
                ]);

            }

            // Sync PR ordered_qty ONLY if PO is directly created as Approved
            if ($po->status === 'Approved') {
                $this->syncPrOrderedQtyOnApproval($po);
            }

            return $po;
        });
    }

    public function updateOrder(PurchaseOrder $order, array $validated): PurchaseOrder
    {
        $deliveryPayload = [
            'location' => array_key_exists('location', $validated) ? $validated['location'] : $order->location,
            'delivery_branch_id' => array_key_exists('delivery_branch_id', $validated) ? $validated['delivery_branch_id'] : $order->delivery_branch_id,
            'delivery_party_branch_id' => array_key_exists('delivery_party_branch_id', $validated) ? $validated['delivery_party_branch_id'] : $order->delivery_party_branch_id,
        ];
        $this->assertSingleDeliveryTarget($deliveryPayload);

        return DB::transaction(function () use ($order, $validated) {
            $discountType = $validated['discount_type'] ?? $order->discount_type ?? 'without_discount';
            $taxType = $validated['tax_type'] ?? $order->tax_type ?? 'without_tax';
            $gstType = $validated['gst_type'] ?? null;
            $vendorId = $validated['vendor_id'] ?? $order->vendor_id;

            $snapshotInput = array_merge([
                'company_id' => $order->company_id,
                'branch_id' => $order->branch_id,
                'vendor_id' => $vendorId,
                'vendor_branch_id' => array_key_exists('vendor_branch_id', $validated) ? $validated['vendor_branch_id'] : $order->vendor_branch_id,
            ], $validated);
            $validated = $this->applyBranchAndGstSnapshots($snapshotInput, (int) $order->tenant_id);

            if ($taxType === 'without_tax') {
                $gstType = $validated['gst_type'] ?? 'cgst_sgst';
            } elseif (empty($gstType) && !empty($vendorId)) {
                $gstType = $this->resolveGstType(
                    (int) $vendorId,
                    $order->company_id,
                    $order->tenant_id,
                    $order->branch_id,
                    $validated['supplier_gstin'] ?? null,
                    $validated['supplier_state_code'] ?? null
                );
            }

            if (empty($gstType)) {
                $gstType = 'cgst_sgst';
            }

            $updateData = [
                'vendor_id' => $vendorId,
                'vendor_branch_id' => $validated['vendor_branch_id'] ?? $order->vendor_branch_id,
                'delivery_branch_id' => array_key_exists('delivery_branch_id', $validated) ? $validated['delivery_branch_id'] : $order->delivery_branch_id,
                'delivery_party_branch_id' => array_key_exists('delivery_party_branch_id', $validated) ? $validated['delivery_party_branch_id'] : $order->delivery_party_branch_id,
                'site_contact_id' => array_key_exists('site_contact_id', $validated) ? $validated['site_contact_id'] : $order->site_contact_id,
                'buyer_gstin' => $validated['buyer_gstin'] ?? $order->buyer_gstin,
                'buyer_state_code' => $validated['buyer_state_code'] ?? $order->buyer_state_code,
                'supplier_gstin' => $validated['supplier_gstin'] ?? $order->supplier_gstin,
                'supplier_state_code' => $validated['supplier_state_code'] ?? $order->supplier_state_code,
                'date' => $validated['date'] ?? $validated['po_date'] ?? $order->date,
                'delivery_date' => $validated['delivery_date'] ?? $validated['expected_delivery_date'] ?? $order->delivery_date,
                'location' => array_key_exists('location', $validated) ? $validated['location'] : $order->location,
                'reference' => $validated['reference'] ?? $order->reference,
                'supplier_quotation_number' => $validated['supplier_quotation_number'] ?? $order->supplier_quotation_number,
                'purchase_requisition_id' => $validated['purchase_requisition_id'] ?? $order->purchase_requisition_id,
                'discount_type' => $discountType,
                'tax_type' => $taxType,
                'gst_type' => $gstType,
                'freight_terms' => $validated['freight_terms'] ?? $order->freight_terms ?? 'to_pay',
                'notes' => $validated['notes'] ?? $order->notes,
            ];

            if (isset($validated['items']) && is_array($validated['items'])) {
                $calculatedSubtotal = 0;
                $calculatedItemDiscount = 0;
                $calculatedItemTax = 0;

                foreach ($validated['items'] as $item) {
                    $qty = (float)($item['quantity'] ?? 0);
                    $price = convert_to_base((float)($item['unit_price'] ?? $item['rate'] ?? 0));
                    $disc = convert_to_base((float)($item['discount'] ?? $item['discount_amount'] ?? 0));
                    $taxRate = ($taxType === 'without_tax') ? 0 : (float)($item['tax_rate'] ?? $item['tax_percent'] ?? 0);

                    $lineAmt = $qty * $price;
                    $calculatedSubtotal += $lineAmt;
                    $calculatedItemDiscount += $disc;
                    $lineTaxable = max(0, $lineAmt - $disc);
                    $calculatedItemTax += ($taxType === 'without_tax') ? 0 : ($lineTaxable * ($taxRate / 100));
                }

                $subtotal = $calculatedSubtotal;

                if ($discountType === 'order_wise') {
                    $discountAmount = convert_to_base((float)($validated['discount_amount'] ?? 0));
                } elseif ($discountType === 'item_wise') {
                    $discountAmount = $calculatedItemDiscount;
                } else {
                    $discountAmount = 0;
                }

                $grossTotal = max(0, $subtotal - $discountAmount);

                if ($taxType === 'order_wise_tax') {
                    $taxAmount = (float)($validated['tax_amount'] ?? 0);
                } elseif ($taxType === 'item_wise_tax') {
                    $taxAmount = $calculatedItemTax;
                } else {
                    $taxAmount = 0;
                }

                $cgstAmount = isset($validated['cgst_amount']) ? (float)$validated['cgst_amount'] : 0;
                $sgstAmount = isset($validated['sgst_amount']) ? (float)$validated['sgst_amount'] : 0;
                $igstAmount = isset($validated['igst_amount']) ? (float)$validated['igst_amount'] : 0;

                if ($taxType === 'without_tax') {
                    $cgstAmount = 0;
                    $sgstAmount = 0;
                    $igstAmount = 0;
                    $taxAmount = 0;
                } elseif ($taxAmount > 0 && ($cgstAmount + $sgstAmount + $igstAmount) <= 0) {
                    if ($gstType === 'igst') {
                        $igstAmount = $taxAmount;
                    } else {
                        $cgstAmount = round($taxAmount / 2, 4);
                        $sgstAmount = round($taxAmount - $cgstAmount, 4);
                    }
                }

                $freightAmt = convert_to_base((float)($validated['freight_amount'] ?? $order->freight_amount ?? 0));
                $isFreightIncluded = in_array($updateData['freight_terms'], ['to_be_billed', 'prepaid']);
                $grandTotal = $grossTotal + $taxAmount + ($isFreightIncluded ? $freightAmt : 0);

                $updateData['subtotal'] = $subtotal;
                $updateData['discount_amount'] = $discountAmount;
                $updateData['tax_amount'] = $taxAmount;
                $updateData['cgst_amount'] = $cgstAmount;
                $updateData['sgst_amount'] = $sgstAmount;
                $updateData['igst_amount'] = $igstAmount;
                $updateData['freight_amount'] = $freightAmt;
                $updateData['grand_total'] = $grandTotal;

                $order->items()->delete();

                foreach ($validated['items'] as $item) {
                    $qty = (float)$item['quantity'];
                    $price = convert_to_base((float)($item['unit_price'] ?? $item['rate'] ?? 0));
                    $disc = convert_to_base((float)($item['discount'] ?? $item['discount_amount'] ?? 0));
                    $lineSubtotal = ($qty * $price) - $disc;

                    if ($taxType === 'item_wise_tax') {
                        $taxRate = (float)($item['tax_rate'] ?? $item['tax_percent'] ?? 0);
                        $lineTax = $lineSubtotal * ($taxRate / 100);
                        $cgstPct = ($gstType === 'cgst_sgst') ? round($taxRate / 2, 4) : 0;
                        $sgstPct = ($gstType === 'cgst_sgst') ? round($taxRate / 2, 4) : 0;
                        $igstPct = ($gstType === 'igst') ? $taxRate : 0;
                        $cgstAmt = ($gstType === 'cgst_sgst') ? round($lineTax / 2, 4) : 0;
                        $sgstAmt = ($gstType === 'cgst_sgst') ? round($lineTax / 2, 4) : 0;
                        $igstAmt = ($gstType === 'igst') ? $lineTax : 0;
                    } else {
                        $taxRate = 0;
                        $lineTax = 0;
                        $cgstPct = 0;
                        $sgstPct = 0;
                        $igstPct = 0;
                        $cgstAmt = 0;
                        $sgstAmt = 0;
                        $igstAmt = 0;
                    }

                    PurchaseOrderItem::create([
                        'purchase_order_id' => $order->id,
                        'product_id' => $item['product_id'] ?? null,
                        'description' => $item['description'] ?? null,
                        'line_type' => $item['line_type'] ?? PurchaseOrderItem::LINE_TYPE_STOCK,
                        'chart_of_account_id' => $item['chart_of_account_id'] ?? null,
                        'asset_category_id' => $item['asset_category_id'] ?? null,
                        'warehouse_id' => $item['warehouse_id'] ?? null,
                        'quantity' => $qty,
                        'rate' => $price,
                        'amount' => $lineSubtotal,
                        'discount_percent' => $item['discount_percent'] ?? 0,
                        'discount_amount' => $disc,
                        'tax_percent' => $taxRate,
                        'cgst_percent' => $cgstPct,
                        'sgst_percent' => $sgstPct,
                        'igst_percent' => $igstPct,
                        'cgst_amount' => $cgstAmt,
                        'sgst_amount' => $sgstAmt,
                        'igst_amount' => $igstAmt,
                        'tax_amount' => $lineTax,
                        'total_amount' => $lineSubtotal + $lineTax,
                    ]);
                }
            }

            $order->update($updateData);

            return $order->fresh(['items', 'vendor']);
        });
    }

    public function confirmOrder(PurchaseOrder $order): bool
    {
        if ($order->status === 'Approved') {
            return true;
        }

        $order->status = 'Approved';
        $updated = $this->orderRepo->update($order, [
            'status' => 'Approved',
        ]);

        if ($updated) {
            $this->syncPrOrderedQtyOnApproval($order);
        }

        return $updated;
    }

    public function approveOrder(PurchaseOrder $order): bool
    {
        return $this->confirmOrder($order);
    }

    public function cancelOrder(PurchaseOrder $order): bool
    {
        $previousStatus = $order->status;
        $order->status = 'Cancelled';
        $updated = $this->orderRepo->update($order, [
            'status' => 'Cancelled',
        ]);

        if ($updated && $previousStatus === 'Approved') {
            $this->revertPrOrderedQtyOnCancel($order);
        }

        return $updated;
    }

    /**
     * Allocate PO item quantities to PR items when PO is Approved
     */
    public function syncPrOrderedQtyOnApproval(PurchaseOrder $po): void
    {
        $po->loadMissing('items');
        $tenantId = $po->tenant_id;

        foreach ($po->items as $item) {
            $qty = (float) $item->quantity;
            $productId = $item->product_id;
            $remainingQtyToAllocate = $qty;

            $candidateQuery = \App\Domains\Purchase\Models\PurchaseRequisitionItem::where('tenant_id', $tenantId)
                ->where('product_id', $productId);

            $requestItemIds = (array) request()->input('requisition_item_ids', []);

            $candidatePrItems = $candidateQuery->get();
            if (!empty($requestItemIds)) {
                $candidatePrItems = $candidatePrItems->sortBy(function ($pi) use ($requestItemIds) {
                    return in_array($pi->id, $requestItemIds) ? 0 : 1;
                });
            } elseif ($po->purchase_requisition_id) {
                $candidatePrItems = $candidatePrItems->sortBy(function ($pi) use ($po) {
                    return $pi->purchase_requisition_id == $po->purchase_requisition_id ? 0 : 1;
                });
            }

            foreach ($candidatePrItems as $prItem) {
                if ($remainingQtyToAllocate <= 0) {
                    break;
                }

                $alreadyOrdered = (float) $prItem->ordered_qty;
                $pendingOnItem = max(0.0, (float) $prItem->quantity - $alreadyOrdered);

                if ($pendingOnItem > 0) {
                    $allocated = min($remainingQtyToAllocate, $pendingOnItem);
                    $prItem->increment('ordered_qty', $allocated);
                    $remainingQtyToAllocate -= $allocated;

                    if (!$po->purchase_requisition_id && $prItem->purchase_requisition_id) {
                        $po->update(['purchase_requisition_id' => $prItem->purchase_requisition_id]);
                    }
                }
            }

            if ($remainingQtyToAllocate > 0 && $candidatePrItems->count() > 0) {
                $lastPrItem = $candidatePrItems->last();
                $lastPrItem->increment('ordered_qty', $remainingQtyToAllocate);
            }
        }
    }

    /**
     * Revert PR ordered_qty if an Approved PO is Cancelled
     */
    public function revertPrOrderedQtyOnCancel(PurchaseOrder $po): void
    {
        $po->loadMissing('items');
        $tenantId = $po->tenant_id;

        foreach ($po->items as $item) {
            $qtyToRevert = (float) $item->quantity;
            $productId = $item->product_id;

            $candidatePrItems = \App\Domains\Purchase\Models\PurchaseRequisitionItem::where('tenant_id', $tenantId)
                ->where('product_id', $productId)
                ->where('ordered_qty', '>', 0)
                ->orderBy('id', 'desc')
                ->get();

            foreach ($candidatePrItems as $prItem) {
                if ($qtyToRevert <= 0) {
                    break;
                }

                $alreadyOrdered = (float) $prItem->ordered_qty;
                $toDecrement = min($qtyToRevert, $alreadyOrdered);

                $prItem->decrement('ordered_qty', $toDecrement);
                $qtyToRevert -= $toDecrement;
            }
        }
    }

    /**
     * Resolve GST type (cgst_sgst for Intra-state, igst for Inter-state)
     * comparing our ordering branch's effective GSTIN/state vs vendor branch's GSTIN/state.
     */
    public function resolveGstType(
        Vendor|int $vendor,
        ?int $companyId = null,
        ?int $tenantId = null,
        ?int $branchId = null,
        ?string $supplierGstin = null,
        ?string $supplierStateCode = null
    ): string {
        if (is_numeric($vendor)) {
            $vendor = Vendor::find($vendor);
        }
        if (!$vendor) {
            return 'cgst_sgst';
        }

        $tenantId ??= $vendor->tenant_id ?? (tenant_id() ?? auth()->user()?->tenant_id ?? 1);
        $branchId ??= (function_exists('branch_id') ? branch_id() : null)
            ?? (function_exists('current_branch_id') ? current_branch_id() : null);

        $branch = $branchId ? Branch::withoutGlobalScopes()->with('company')->find($branchId) : null;
        $company = $branch?->company;

        if (!$company && $companyId) {
            $company = Company::withoutGlobalScopes()->find($companyId);
        }
        if (!$company && function_exists('company') && company()) {
            $company = company();
        }
        if (!$company && function_exists('current_company_id') && current_company_id()) {
            $company = Company::withoutGlobalScopes()->find(current_company_id());
        }
        if (!$company) {
            $company = Company::withoutGlobalScopes()->where('tenant_id', $tenantId)->first();
        }

        // Buyer effective GSTIN: ordering branch -> branch GstConfig -> company -> default GstConfig
        $buyerGstin = $branch?->gst_number ? TaxIdentityService::normalizeGstin($branch->gst_number) : null;
        if (empty($buyerGstin)) {
            $gstConfig = GstConfiguration::getForCurrentContext($company?->id, $branch?->id);
            if ($gstConfig && $gstConfig->branch_id && $gstConfig->seller_gstin) {
                $buyerGstin = TaxIdentityService::normalizeGstin($gstConfig->seller_gstin);
            }
        }
        if (empty($buyerGstin)) {
            $buyerGstin = TaxIdentityService::normalizeGstin($company?->gst_number ?? $company?->tax_number ?? $company?->gstin ?? null);
        }
        if (empty($buyerGstin)) {
            $gstConfig = GstConfiguration::getForCurrentContext($company?->id, $branch?->id);
            if ($gstConfig && $gstConfig->seller_gstin) {
                $buyerGstin = TaxIdentityService::normalizeGstin($gstConfig->seller_gstin);
            }
        }

        $buyerStateCode = ($branch && $branch->gst_number && $branch->gst_state_code)
            ? str_pad((string) $branch->gst_state_code, 2, '0', STR_PAD_LEFT)
            : TaxIdentityService::extractStateCode($buyerGstin);

        $resolvedSupplierState = $supplierStateCode
            ? str_pad((string) $supplierStateCode, 2, '0', STR_PAD_LEFT)
            : TaxIdentityService::extractStateCode($supplierGstin ?: ($vendor->gstin ?? $vendor->tax_number ?? null));

        // 1. Primary rule: Compare 2-digit GST state codes
        if (!empty($buyerStateCode) && !empty($resolvedSupplierState)) {
            return ($buyerStateCode === $resolvedSupplierState) ? 'cgst_sgst' : 'igst';
        }

        // 2. Fallback: Compare state names if GSTINs not available
        $buyerStateName = strtolower(trim((string) (($branch && $branch->gst_number ? $branch->state : null) ?: $company?->state ?: $branch?->state ?: '')));
        $vendorState = strtolower(trim((string) ($vendor->billing_state ?? $vendor->state ?? '')));
        if (!empty($buyerStateName) && !empty($vendorState)) {
            return ($buyerStateName === $vendorState) ? 'cgst_sgst' : 'igst';
        }

        return 'cgst_sgst';
    }

    /**
     * Validate that a Purchase Order specifies exactly ONE delivery target:
     * our warehouse (`location`), our branch (`delivery_branch_id`), or a customer site (`delivery_party_branch_id`).
     */
    public function assertSingleDeliveryTarget(array $validated): void
    {
        $hasWarehouse = ! empty(trim((string) ($validated['location'] ?? '')));
        $hasOwnBranch = ! empty($validated['delivery_branch_id']);
        $hasCustomerSite = ! empty($validated['delivery_party_branch_id']);

        $count = ($hasWarehouse ? 1 : 0) + ($hasOwnBranch ? 1 : 0) + ($hasCustomerSite ? 1 : 0);

        if ($count > 1) {
            throw ValidationException::withMessages([
                'delivery_target' => 'Choose exactly one delivery target on a Purchase Order: our warehouse, our branch, or a customer site (not multiple).',
            ]);
        }

        // Enforce at-least-one target when branch delivery rules are explicitly used or branches feature is enabled
        // while keeping legacy callers that omit location completely working only if explicitly disabled.
        if ($count === 0 && (bool) config('party_verification.branches_enabled', true) && array_key_exists('enforce_delivery_target', $validated) && $validated['enforce_delivery_target']) {
            throw ValidationException::withMessages([
                'delivery_target' => 'A delivery target is required: choose our warehouse, our branch, or a customer site.',
            ]);
        }
    }

    /**
     * Freeze buyer_gstin/buyer_state_code (from ordering branch_id) and supplier_gstin/supplier_state_code
     * (from vendor_branch_id or vendor default branch) onto the PO payload.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function applyBranchAndGstSnapshots(array $validated, int $tenantId): array
    {
        $branchId = $validated['branch_id'] ?? current_branch_id() ?? auth()->user()?->branch_id;
        $ownBranch = $branchId ? Branch::withoutGlobalScopes()->with('company')->find($branchId) : null;
        $buyerOrg = billing_organization_details($ownBranch);

        if (empty($validated['buyer_gstin']) && ! empty($buyerOrg['gstin'])) {
            $validated['buyer_gstin'] = $buyerOrg['gstin'];
        }
        if (empty($validated['buyer_state_code']) && ! empty($buyerOrg['state_code'])) {
            $validated['buyer_state_code'] = $buyerOrg['state_code'];
        }

        $vendor = ! empty($validated['vendor_id'])
            ? Vendor::withoutGlobalScopes()->where('tenant_id', $tenantId)->find($validated['vendor_id'])
            : null;

        if ($vendor) {
            $vendorBranch = ! empty($validated['vendor_branch_id'])
                ? PartyBranch::withoutGlobalScopes()->find($validated['vendor_branch_id'])
                : $vendor->defaultBillingBranch();

            if ($vendorBranch && empty($validated['vendor_branch_id'])) {
                $validated['vendor_branch_id'] = $vendorBranch->id;
            }

            $supplierGstin = $vendorBranch?->effectiveGstin() ?: TaxIdentityService::normalizeGstin($vendor->gstin);
            $supplierStateCode = $vendorBranch?->effectiveStateCode() ?: TaxIdentityService::extractStateCode($supplierGstin);

            $validated['supplier_gstin'] = $validated['supplier_gstin'] ?? $supplierGstin;
            $validated['supplier_state_code'] = $validated['supplier_state_code'] ?? $supplierStateCode;
        }

        return $validated;
    }
}
