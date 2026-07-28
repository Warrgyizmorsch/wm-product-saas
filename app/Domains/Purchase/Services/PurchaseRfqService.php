<?php

namespace App\Domains\Purchase\Services;

use App\Domains\Purchase\Models\PurchaseRfq;
use App\Domains\Purchase\Models\PurchaseRfqItem;
use App\Domains\Purchase\Models\PurchaseRfqVendor;
use App\Domains\Purchase\Models\PurchaseRfqVendorRate;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Domains\Purchase\Models\PurchaseOrderItem;
use App\Domains\Purchase\Repositories\PurchaseRfqRepository;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Inventory\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PurchaseRfqService
{
    public function __construct(
        protected PurchaseRfqRepository $rfqRepo
    ) {}

    public function storeRfq(array $validated, int $tenantId): PurchaseRfq
    {
        return DB::transaction(function () use ($validated, $tenantId) {
            $year = now()->format('Y');
            $prefix = "RFQ-{$year}-";
            $latest = PurchaseRfq::where('tenant_id', $tenantId)
                ->where('rfq_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($latest) {
                $lastNumStr = str_replace($prefix, '', $latest->rfq_number);
                $nextNum = intval($lastNumStr) + 1;
            }
            $rfqNumber = $prefix . str_pad($nextNum, 4, '0', STR_PAD_LEFT);

            $rfq = PurchaseRfq::create([
                'tenant_id' => $tenantId,
                'rfq_number' => $rfqNumber,
                'purchase_requisition_id' => $validated['purchase_requisition_id'] ?? null,
                'rfq_date' => $validated['rfq_date'],
                'status' => 'Draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id() ?: 1,
            ]);

            $uniqueVendorIds = [];
            foreach ($validated['items'] as $itemData) {
                $rfqItem = PurchaseRfqItem::create([
                    'purchase_rfq_id' => $rfq->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'estimated_cost' => $itemData['estimated_cost'] ?? 0.00,
                ]);

                if (isset($itemData['vendor_ids']) && is_array($itemData['vendor_ids'])) {
                    foreach ($itemData['vendor_ids'] as $vendorId) {
                        $uniqueVendorIds[$vendorId] = $vendorId;
                        DB::table('purchase_rfq_item_vendors')->insert([
                            'tenant_id' => $tenantId,
                            'purchase_rfq_item_id' => $rfqItem->id,
                            'vendor_id' => $vendorId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            foreach (array_values($uniqueVendorIds) as $vendorId) {
                PurchaseRfqVendor::create([
                    'tenant_id' => $tenantId,
                    'purchase_rfq_id' => $rfq->id,
                    'vendor_id' => $vendorId,
                    'token' => Str::random(40),
                    'status' => 'Sent',
                ]);
            }

            return $rfq;
        });
    }

    public function updateRfq(PurchaseRfq $rfq, array $validated, int $tenantId): PurchaseRfq
    {
        return DB::transaction(function () use ($validated, $rfq, $tenantId) {
            $rfq->update([
                'purchase_requisition_id' => $validated['purchase_requisition_id'] ?? null,
                'rfq_date' => $validated['rfq_date'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $rfq->items()->delete();

            $uniqueVendorIds = [];
            foreach ($validated['items'] as $itemData) {
                $rfqItem = PurchaseRfqItem::create([
                    'purchase_rfq_id' => $rfq->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $itemData['quantity'],
                    'estimated_cost' => $itemData['estimated_cost'] ?? 0.00,
                ]);

                if (isset($itemData['vendor_ids']) && is_array($itemData['vendor_ids'])) {
                    foreach ($itemData['vendor_ids'] as $vendorId) {
                        $uniqueVendorIds[$vendorId] = $vendorId;
                        DB::table('purchase_rfq_item_vendors')->insert([
                            'tenant_id' => $tenantId,
                            'purchase_rfq_item_id' => $rfqItem->id,
                            'vendor_id' => $vendorId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }

            $existingVendors = $rfq->rfqVendors;
            $newVendorIds = array_values($uniqueVendorIds);

            foreach ($existingVendors as $ev) {
                if (!in_array($ev->vendor_id, $newVendorIds)) {
                    $ev->delete();
                }
            }

            $existingVendorIds = $existingVendors->pluck('vendor_id')->toArray();
            foreach ($newVendorIds as $vendorId) {
                if (!in_array($vendorId, $existingVendorIds)) {
                    PurchaseRfqVendor::create([
                        'tenant_id' => $tenantId,
                        'purchase_rfq_id' => $rfq->id,
                        'vendor_id' => $vendorId,
                        'token' => Str::random(40),
                        'status' => 'Sent',
                    ]);
                }
            }

            return $rfq;
        });
    }

    public function createPoFromRfq(PurchaseRfq $rfq, array $validated, int $tenantId): PurchaseOrder
    {
        return DB::transaction(function () use ($validated, $rfq, $tenantId) {
            $warehouse = Warehouse::where('tenant_id', $tenantId)
                ->where('name', $validated['location'])
                ->first();
            $warehouseId = $warehouse ? $warehouse->id : null;

            $year = now()->format('Y');
            $prefix = "PO-{$year}-";
            $latest = PurchaseOrder::where('tenant_id', $tenantId)
                ->where('purchase_order_number', 'like', "{$prefix}%")
                ->orderBy('id', 'desc')
                ->first();
            $nextNum = 1;
            if ($latest) {
                $lastNumStr = str_replace($prefix, '', $latest->purchase_order_number);
                $nextNum = ((int) $lastNumStr) + 1;
            }
            $poNumber = $prefix . str_pad($nextNum, 6, '0', STR_PAD_LEFT);

            $rfqVendor = $rfq->rfqVendors()->where('vendor_id', $validated['vendor_id'])->first();
            $quoteNo = $validated['supplier_quotation_number'] ?? ($rfqVendor?->quotation_number);

            $po = PurchaseOrder::create([
                'tenant_id' => $tenantId,
                'purchase_order_number' => $poNumber,
                'purchase_requisition_id' => $rfq->purchase_requisition_id,
                'source_type' => 'rfq',
                'vendor_id' => $validated['vendor_id'],
                'location' => $validated['location'],
                'reference' => $validated['reference'] ?? $rfq->rfq_number,
                'supplier_quotation_number' => $quoteNo,
                'date' => $validated['date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'discount_type' => $validated['discount_type'],
                'tax_type' => $validated['tax_type'],
                'gst_type' => $validated['gst_type'],
                'subtotal' => $validated['subtotal'],
                'discount_amount' => $validated['discount_amount'],
                'cgst_amount' => $validated['cgst_amount'],
                'sgst_amount' => $validated['sgst_amount'],
                'igst_amount' => $validated['igst_amount'],
                'tax_amount' => $validated['tax_amount'],
                'grand_total' => $validated['grand_total'],
                'status' => 'Draft',
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id() ?: 1,
            ]);

            foreach ($validated['items'] as $item) {
                $amount = $item['quantity'] * $item['rate'];
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'product_id' => $item['product_id'],
                    'warehouse_id' => $warehouseId,
                    'quantity' => $item['quantity'],
                    'rate' => $item['rate'],
                    'amount' => $amount,
                    'discount_percent' => $item['discount_percent'] ?? 0.00,
                    'discount_amount' => $item['discount_amount'] ?? 0.00,
                    'tax_percent' => $item['tax_percent'] ?? 0.00,
                    'cgst_percent' => $item['cgst_percent'] ?? 0.00,
                    'sgst_percent' => $item['sgst_percent'] ?? 0.00,
                    'igst_percent' => $item['igst_percent'] ?? 0.00,
                    'cgst_amount' => $item['cgst_amount'] ?? 0.00,
                    'sgst_amount' => $item['sgst_amount'] ?? 0.00,
                    'igst_amount' => $item['igst_amount'] ?? 0.00,
                    'tax_amount' => $item['tax_amount'] ?? 0.00,
                    'total_amount' => $item['total_amount'] ?? $amount,
                ]);
            }

            $rfq->update(['status' => 'Confirmed']);

            return $po;
        });
    }

    public function getSavingsDashboardData($request, int $tenantId, $user): array
    {
        $isAdmin = in_array($user->role ?? '', ['admin', 'super_admin', 'tenant_owner', 'company_admin']);

        $poQuery = PurchaseOrder::where('tenant_id', $tenantId)
            ->where('source_type', 'rfq')
            ->with(['vendor', 'creator', 'items.product', 'requisition']);

        if (!$isAdmin) {
            $poQuery->where('created_by', $user->id);
        } elseif ($request->filled('purchaser_id')) {
            $poQuery->where('created_by', $request->input('purchaser_id'));
        }

        if ($request->filled('date_from')) {
            $poQuery->whereDate('date', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $poQuery->whereDate('date', '<=', $request->input('date_to'));
        }
        if ($request->filled('vendor_id')) {
            $poQuery->where('vendor_id', $request->input('vendor_id'));
        }
        if ($request->filled('po_number')) {
            $poQuery->where('purchase_order_number', 'like', '%' . $request->input('po_number') . '%');
        }
        if ($request->filled('rfq_number')) {
            $searchRfq = $request->input('rfq_number');
            $poQuery->where('reference', 'like', '%' . $searchRfq . '%');
        }
        if ($request->filled('product_id')) {
            $productId = $request->input('product_id');
            $poQuery->whereHas('items', function ($q) use ($productId) {
                $q->where('product_id', $productId);
            });
        }
        if ($request->filled('department')) {
            $dept = $request->input('department');
            $poQuery->whereHas('requisition', function ($q) use ($dept) {
                $q->where('department', 'like', '%' . $dept . '%');
            });
        }

        $allOrders = $poQuery->orderBy('id', 'desc')->get();

        $processedOrders = [];
        $totalSavings = 0;
        $totalSpend = 0;
        $highestSingleSavings = 0;
        $bestPurchaserName = 'N/A';
        $purchaserSavings = [];
        $deptSavings = [];
        $vendorStats = [];
        $monthlySavings = array_fill(1, 12, 0);

        foreach ($allOrders as $order) {
            $poTotal = (float) $order->grand_total;
            $totalSpend += $poTotal;

            $rfqNumber = $order->reference ? str_replace('RFQ: ', '', $order->reference) : null;
            $rfq = null;
            if ($rfqNumber) {
                $rfq = PurchaseRfq::where('tenant_id', $tenantId)
                    ->where('rfq_number', $rfqNumber)
                    ->with('rfqVendors.rates')
                    ->first();
            }

            $poHighestQuoteTotal = 0;
            $poSavings = 0;

            foreach ($order->items as $item) {
                $qty = (float) $item->quantity;
                $poRate = (float) $item->rate;

                $highestRate = $poRate;
                if ($rfq) {
                    $allVendorRates = [];
                    foreach ($rfq->rfqVendors as $rv) {
                        foreach ($rv->rates as $vRate) {
                            if ((int)$vRate->product_id === (int)$item->product_id && (float)$vRate->rate > 0) {
                                $allVendorRates[] = (float)$vRate->rate;
                            }
                        }
                    }
                    if (!empty($allVendorRates)) {
                        $highestRate = max($allVendorRates);
                    }
                }

                if ($highestRate <= $poRate && $item->product?->estimated_cost > $poRate) {
                    $highestRate = (float) $item->product->estimated_cost;
                }

                $itemHighestTotal = $highestRate * $qty;
                $poHighestQuoteTotal += $itemHighestTotal;
                $itemSavings = max(0, $itemHighestTotal - ($poRate * $qty));
                $poSavings += $itemSavings;
            }

            $totalSavings += $poSavings;
            if ($poSavings > $highestSingleSavings) {
                $highestSingleSavings = $poSavings;
            }

            $savingPercent = $poHighestQuoteTotal > 0 ? ($poSavings / $poHighestQuoteTotal) * 100 : 0;

            $creatorId = $order->created_by ?: 0;
            $creatorName = $order->creator?->name ?? 'System User';
            if (!isset($purchaserSavings[$creatorId])) {
                $purchaserSavings[$creatorId] = [
                    'id' => $creatorId,
                    'name' => $creatorName,
                    'po_count' => 0,
                    'total_spend' => 0,
                    'total_savings' => 0,
                ];
            }
            $purchaserSavings[$creatorId]['po_count']++;
            $purchaserSavings[$creatorId]['total_spend'] += $poTotal;
            $purchaserSavings[$creatorId]['total_savings'] += $poSavings;

            $deptName = $order->requisition?->department ?? 'General Procurement';
            if (!isset($deptSavings[$deptName])) {
                $deptSavings[$deptName] = [
                    'department' => $deptName,
                    'total_spend' => 0,
                    'total_savings' => 0,
                ];
            }
            $deptSavings[$deptName]['total_spend'] += $poTotal;
            $deptSavings[$deptName]['total_savings'] += $poSavings;

            $vId = $order->vendor_id;
            $vName = $order->vendor?->name ?? 'Unknown Vendor';
            if (!isset($vendorStats[$vId])) {
                $vendorStats[$vId] = [
                    'vendor_id' => $vId,
                    'name' => $vName,
                    'rfqs_won' => 0,
                    'total_spend' => 0,
                    'total_savings' => 0,
                ];
            }
            $vendorStats[$vId]['rfqs_won']++;
            $vendorStats[$vId]['total_spend'] += $poTotal;
            $vendorStats[$vId]['total_savings'] += $poSavings;

            $monthNum = (int) ($order->date ? $order->date->format('n') : now()->format('n'));
            $monthlySavings[$monthNum] += $poSavings;

            $processedOrders[] = [
                'id' => $order->id,
                'order' => $order,
                'po_number' => $order->purchase_order_number,
                'rfq_number' => $rfqNumber ?: ($rfq?->rfq_number ?? '—'),
                'supplier_quotation_number' => $order->supplier_quotation_number ?: '—',
                'purchaser_name' => $creatorName,
                'vendor_name' => $vName,
                'po_amount' => $poTotal,
                'highest_quote_amount' => $poHighestQuoteTotal,
                'savings_amount' => $poSavings,
                'savings_percent' => round($savingPercent, 2),
                'status' => $order->status,
                'date' => $order->date ? $order->date->format('d-M-Y') : '—',
            ];
        }

        uasort($purchaserSavings, fn($a, $b) => $b['total_savings'] <=> $a['total_savings']);
        $topPurchaser = reset($purchaserSavings);
        if ($topPurchaser && $topPurchaser['total_savings'] > 0) {
            $bestPurchaserName = $topPurchaser['name'];
        }

        uasort($vendorStats, fn($a, $b) => $b['total_savings'] <=> $a['total_savings']);
        uasort($deptSavings, fn($a, $b) => $b['total_savings'] <=> $a['total_savings']);

        $avgSavingPercent = ($totalSpend + $totalSavings) > 0 ? ($totalSavings / ($totalSpend + $totalSavings)) * 100 : 0;

        $allPurchasers = User::where('tenant_id', $tenantId)->get(['id', 'name']);
        $allVendors = Vendor::where('tenant_id', $tenantId)->get(['id', 'name']);
        $allProducts = Product::where('tenant_id', $tenantId)->get(['id', 'name', 'sku']);

        return compact(
            'isAdmin',
            'processedOrders',
            'totalSavings',
            'totalSpend',
            'highestSingleSavings',
            'bestPurchaserName',
            'avgSavingPercent',
            'purchaserSavings',
            'deptSavings',
            'vendorStats',
            'monthlySavings',
            'allPurchasers',
            'allVendors',
            'allProducts'
        );
    }
}
