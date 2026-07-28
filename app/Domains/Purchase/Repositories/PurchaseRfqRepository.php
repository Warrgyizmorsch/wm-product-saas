<?php

namespace App\Domains\Purchase\Repositories;

use App\Domains\Purchase\Models\PurchaseRfq;
use App\Domains\Purchase\Models\PurchaseOrder;
use App\Models\User;

class PurchaseRfqRepository
{
    public function getPaginatedRfqsData(array $filters = [], int $perPage = 10): array
    {
        $tenantId = require_tenant_id();
        $user = auth()->user();
        $isAdmin = in_array($user->role ?? '', ['admin', 'super_admin', 'tenant_owner', 'company_admin']);

        $query = PurchaseRfq::where('tenant_id', $tenantId)
            ->with(['rfqVendors.vendor', 'rfqVendors.rates', 'requisition', 'creator', 'items.product']);

        if (! $isAdmin) {
            $query->where('created_by', $user?->id);
        } elseif (!empty($filters['created_by'])) {
            $query->where('created_by', $filters['created_by']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . trim($filters['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('rfq_number', 'like', $search)
                  ->orWhereHas('rfqVendors.vendor', function ($v) use ($search) {
                      $v->where('name', 'like', $search);
                  })
                  ->orWhereHas('creator', function ($c) use ($search) {
                      $c->where('name', 'like', $search);
                  });
            });
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date_from'])) {
            $query->whereDate('rfq_date', '>=', $filters['date_from']);
        }
        if (!empty($filters['date_to'])) {
            $query->whereDate('rfq_date', '<=', $filters['date_to']);
        }

        $sortBy = $filters['sort_by'] ?? 'id';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $allowedSorts = ['id', 'rfq_number', 'rfq_date', 'status'];

        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $allFilteredRfqs = (clone $query)->get();
        $totalFilteredCount = $allFilteredRfqs->count();
        $totalFilteredSavings = 0;
        $totalFilteredSpend = 0;

        $rfqNumbers = $allFilteredRfqs->pluck('rfq_number')->filter()->toArray();
        $matchingPos = PurchaseOrder::where('tenant_id', $tenantId)
            ->where('source_type', 'rfq')
            ->where(function ($q) use ($rfqNumbers) {
                foreach ($rfqNumbers as $rfqNum) {
                    $q->orWhere('reference', 'like', '%' . $rfqNum . '%');
                }
            })
            ->with(['items'])
            ->get();

        $poByRfqMap = [];
        foreach ($matchingPos as $po) {
            if ($po->reference && preg_match('/RFQ:\s*([^\s\|]+)/i', $po->reference, $matches)) {
                $num = trim($matches[1]);
                $poByRfqMap[$num] = $po;
            } else {
                $num = str_replace('RFQ: ', '', $po->reference);
                $poByRfqMap[$num] = $po;
            }
        }

        $calcRfqSavings = function ($rfq) use ($poByRfqMap) {
            $rfqNum = $rfq->rfq_number;
            $po = $poByRfqMap[$rfqNum] ?? null;
            if (! $po) {
                return ['savings' => 0, 'spend' => 0, 'percent' => 0, 'po_number' => null];
            }

            $poTotal = (float) $po->grand_total;
            $poHighestTotal = 0;
            $poSavings = 0;

            foreach ($po->items as $item) {
                $qty = (float) $item->quantity;
                $poRate = (float) $item->rate;

                $highestRate = $poRate;
                $allVendorRates = [];
                foreach ($rfq->rfqVendors as $rv) {
                    foreach ($rv->rates as $vRate) {
                        if ((int)$vRate->product_id === (int)$item->product_id && (float)$vRate->rate > 0) {
                            $allVendorRates[] = (float)$vRate->rate;
                        }
                    }
                }
                if (! empty($allVendorRates)) {
                    $highestRate = max($allVendorRates);
                }

                if ($highestRate <= $poRate && $item->product?->estimated_cost > $poRate) {
                    $highestRate = (float) $item->product->estimated_cost;
                }

                $itemHighestTotal = $highestRate * $qty;
                $poHighestTotal += $itemHighestTotal;
                $poSavings += max(0, $itemHighestTotal - ($poRate * $qty));
            }

            $percent = $poHighestTotal > 0 ? ($poSavings / $poHighestTotal) * 100 : 0;
            return [
                'savings' => $poSavings,
                'spend' => $poTotal,
                'percent' => round($percent, 2),
                'po_number' => $po->purchase_order_number ?? $po->po_number,
            ];
        };

        foreach ($allFilteredRfqs as $rItem) {
            $sData = $calcRfqSavings($rItem);
            $totalFilteredSavings += $sData['savings'];
            $totalFilteredSpend += $sData['spend'];
        }

        $rfqs = $query->paginate($perPage)->withQueryString();

        foreach ($rfqs as $rfqItem) {
            $sData = $calcRfqSavings($rfqItem);
            $rfqItem->savings_amount = $sData['savings'];
            $rfqItem->savings_percent = $sData['percent'];
            $rfqItem->po_number = $sData['po_number'];
        }

        $allPurchasers = User::where('tenant_id', $tenantId)->get(['id', 'name']);

        return compact(
            'rfqs',
            'isAdmin',
            'allPurchasers',
            'totalFilteredCount',
            'totalFilteredSavings',
            'totalFilteredSpend'
        );
    }

    public function find(int $id): ?PurchaseRfq
    {
        $tenantId = require_tenant_id();
        return PurchaseRfq::where('tenant_id', $tenantId)->find($id);
    }

    public function findWithDetails(int $id): PurchaseRfq
    {
        $tenantId = require_tenant_id();
        return PurchaseRfq::where('tenant_id', $tenantId)
            ->with([
                'requisition',
                'items.product',
                'items.vendors',
                'rfqVendors.vendor',
                'rfqVendors.rates.product'
            ])
            ->findOrFail($id);
    }

    public function create(array $data): PurchaseRfq
    {
        return PurchaseRfq::create($data);
    }

    public function update(PurchaseRfq $rfq, array $data): bool
    {
        return $rfq->update($data);
    }

    public function delete(PurchaseRfq $rfq): ?bool
    {
        return $rfq->delete();
    }
}
