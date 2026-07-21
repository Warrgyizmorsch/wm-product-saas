<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionCostAdjustment;
use App\Domains\Production\Models\ProductionOrder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductionCostAdjustmentService
{
    /**
     * Create a manual cost adjustment with safe file upload handling and timeline event writing.
     */
    public function createAdjustment(ProductionOrder $order, array $data, ?UploadedFile $file = null, ?int $userId = null): ProductionCostAdjustment
    {
        $newAttachment = null;
        if ($file) {
            $newAttachment = $file->store('cost_adjustments', 'local');
        }

        try {
            return DB::transaction(function () use ($order, $data, $newAttachment, $userId) {
                $adjustment = ProductionCostAdjustment::create([
                    'tenant_id'           => $order->tenant_id,
                    'production_order_id' => $order->id,
                    'adjustment_date'     => $data['adjustment_date'],
                    'cost_component'      => $data['cost_component'],
                    'category'            => $data['category'],
                    'description'         => $data['description'],
                    'amount'              => $data['amount'],
                    'attachment_path'     => $newAttachment,
                    'status'              => 'recorded',
                    'notes'               => $data['notes'] ?? null,
                    'created_by'          => $userId ?? auth()->id(),
                    'updated_by'          => $userId ?? auth()->id(),
                ]);

                if (class_exists(ProductionEventService::class)) {
                    $amountFmt = number_format((float) $adjustment->amount, 2);
                    app(ProductionEventService::class)->writeEvent($order->tenant_id, [
                        'production_order_id' => $order->id,
                        'event_type'          => 'cost_adjustment_added',
                        'title'               => 'Manual Cost Adjustment Added',
                        'description'         => "Cost adjustment #{$adjustment->id} of {$amountFmt} ({$adjustment->cost_component}/{$adjustment->category}) added: {$adjustment->description} (Date: {$adjustment->adjustment_date->format('Y-m-d')}).",
                        'severity'            => 'warning',
                        'triggered_by'        => $userId ?? auth()->id(),
                    ]);
                }

                return $adjustment;
            });
        } catch (\Exception $e) {
            if ($newAttachment && Storage::disk('local')->exists($newAttachment)) {
                Storage::disk('local')->delete($newAttachment);
            }
            throw $e;
        }
    }

    /**
     * Update a manual cost adjustment with file preservation and transaction rollback safety.
     */
    public function updateAdjustment(ProductionCostAdjustment $adjustment, array $data, ?UploadedFile $file = null, ?int $userId = null): ProductionCostAdjustment
    {
        $oldAttachment = $adjustment->attachment_path;
        $newAttachment = null;

        if ($file) {
            $newAttachment = $file->store('cost_adjustments', 'local');
        }

        $oldAmount    = $adjustment->amount;
        $oldComponent = $adjustment->cost_component;
        $oldCategory  = $adjustment->category;

        try {
            return DB::transaction(function () use ($adjustment, $data, $newAttachment, $oldAttachment, $oldAmount, $oldComponent, $oldCategory, $userId) {
                $updateData = [
                    'cost_component'  => $data['cost_component'],
                    'category'        => $data['category'],
                    'description'     => $data['description'],
                    'amount'          => $data['amount'],
                    'adjustment_date' => $data['adjustment_date'],
                    'notes'           => $data['notes'] ?? null,
                    'updated_by'      => $userId ?? auth()->id(),
                ];

                if ($newAttachment) {
                    $updateData['attachment_path'] = $newAttachment;
                }

                $adjustment->update($updateData);

                if (class_exists(ProductionEventService::class)) {
                    app(ProductionEventService::class)->writeEvent($adjustment->tenant_id, [
                        'production_order_id' => $adjustment->production_order_id,
                        'event_type'          => 'cost_adjustment_updated',
                        'title'               => 'Manual Cost Adjustment Updated',
                        'description'         => "Cost adjustment #{$adjustment->id} updated. Amount: {$oldAmount} -> {$adjustment->amount}, Component: {$oldComponent} -> {$adjustment->cost_component}, Category: {$oldCategory} -> {$adjustment->category}.",
                        'severity'            => 'info',
                        'triggered_by'        => $userId ?? auth()->id(),
                    ]);
                }

                if ($newAttachment && $oldAttachment && Storage::disk('local')->exists($oldAttachment)) {
                    Storage::disk('local')->delete($oldAttachment);
                }

                return $adjustment->fresh();
            });
        } catch (\Exception $e) {
            if ($newAttachment && Storage::disk('local')->exists($newAttachment)) {
                Storage::disk('local')->delete($newAttachment);
            }
            throw $e;
        }
    }

    /**
     * Soft-delete a cost adjustment and record timeline audit entry.
     */
    public function deleteAdjustment(ProductionCostAdjustment $adjustment, ?int $userId = null): bool
    {
        return DB::transaction(function () use ($adjustment, $userId) {
            $amountFmt = number_format((float) $adjustment->amount, 2);
            $orderId   = $adjustment->production_order_id;
            $tenantId  = $adjustment->tenant_id;
            $adjId     = $adjustment->id;
            $comp      = $adjustment->cost_component;
            $cat       = $adjustment->category;

            $deleted = $adjustment->delete();

            if ($deleted && class_exists(ProductionEventService::class)) {
                app(ProductionEventService::class)->writeEvent($tenantId, [
                    'production_order_id' => $orderId,
                    'event_type'          => 'cost_adjustment_deleted',
                    'title'               => 'Manual Cost Adjustment Soft-Deleted',
                    'description'         => "Cost adjustment #{$adjId} of {$amountFmt} ({$comp}/{$cat}) was soft-deleted.",
                    'severity'            => 'warning',
                    'triggered_by'        => $userId ?? auth()->id(),
                ]);
            }

            return $deleted;
        });
    }

    /**
     * Aggregate adjustments by component for a given production order.
     */
    public function getAdjustmentTotalsByComponent(ProductionOrder $order): array
    {
        $rows = ProductionCostAdjustment::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->selectRaw('cost_component, SUM(amount) as total_amount')
            ->groupBy('cost_component')
            ->pluck('total_amount', 'cost_component')
            ->all();

        $material = (float) ($rows['material'] ?? 0.0);
        $labor    = (float) ($rows['labor'] ?? 0.0);
        $machine  = (float) ($rows['machine'] ?? 0.0);
        $overhead = (float) ($rows['overhead'] ?? 0.0);
        $other    = (float) ($rows['other'] ?? 0.0);
        $total    = $material + $labor + $machine + $overhead + $other;

        return [
            'material' => $material,
            'labor'    => $labor,
            'machine'  => $machine,
            'overhead' => $overhead,
            'other'    => $other,
            'total'    => $total,
        ];
    }

    /**
     * Get active manual adjustments grouped by adjustment_date (Y-m-d).
     */
    public function getDailyAdjustments(ProductionOrder $order): array
    {
        $daily = [];
        $adjustments = ProductionCostAdjustment::where('tenant_id', $order->tenant_id)
            ->where('production_order_id', $order->id)
            ->get();

        foreach ($adjustments as $adj) {
            $dateStr = $adj->adjustment_date ? $adj->adjustment_date->format('Y-m-d') : $adj->created_at->format('Y-m-d');
            $daily[$dateStr] = ($daily[$dateStr] ?? 0.0) + (float) $adj->amount;
        }

        return $daily;
    }

    /**
     * Merge automatic cost calculations with manual adjustments to produce Final Costing Summary.
     */
    public function getFinalCostingSummary(ProductionOrder $order, array $automaticCosts): array
    {
        $manual = $this->getAdjustmentTotalsByComponent($order);

        $materialAuto = (float) ($automaticCosts['material']['actual'] ?? 0.0);
        $laborAuto    = (float) ($automaticCosts['labor']['actual'] ?? 0.0);
        $machineAuto  = (float) ($automaticCosts['machine']['actual'] ?? 0.0);
        $overheadAuto = (float) ($automaticCosts['overhead']['actual'] ?? 0.0);
        $otherAuto    = 0.0;
        $totalAuto    = (float) ($automaticCosts['totals']['actual'] ?? 0.0);

        return [
            'material' => [
                'auto'   => $materialAuto,
                'manual' => $manual['material'],
                'final'  => $materialAuto + $manual['material'],
            ],
            'labor' => [
                'auto'   => $laborAuto,
                'manual' => $manual['labor'],
                'final'  => $laborAuto + $manual['labor'],
            ],
            'machine' => [
                'auto'   => $machineAuto,
                'manual' => $manual['machine'],
                'final'  => $machineAuto + $manual['machine'],
            ],
            'overhead' => [
                'auto'   => $overheadAuto,
                'manual' => $manual['overhead'],
                'final'  => $overheadAuto + $manual['overhead'],
            ],
            'other' => [
                'auto'   => $otherAuto,
                'manual' => $manual['other'],
                'final'  => $otherAuto + $manual['other'],
            ],
            'totals' => [
                'auto'   => $totalAuto,
                'manual' => $manual['total'],
                'final'  => $totalAuto + $manual['total'],
            ],
        ];
    }
}
