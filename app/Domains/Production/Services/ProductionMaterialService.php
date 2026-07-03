<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionOrderReservation;
use App\Domains\Production\Models\ProductionOrderIssue;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProductionMaterialService
{
    /**
     * Reserve material quantities for a reservation.
     */
    public function reserveMaterial(int $reservationId, float $quantity): ProductionOrderReservation
    {
        $res = ProductionOrderReservation::findOrFail($reservationId);
        $res->quantity_reserved += $quantity;
        $res->save();
        return $res;
    }

    /**
     * Issue material quantities against a reservation snapshot.
     */
    public function issueMaterial(int $reservationId, float $quantity, ?string $remarks = null, ?int $userId = null): ProductionOrderIssue
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Quantity to issue must be greater than zero.");
        }

        return DB::transaction(function () use ($reservationId, $quantity, $remarks, $userId) {
            $res = ProductionOrderReservation::findOrFail($reservationId);
            
            // If total issued exceeds planned, mark as additional issue
            $type = ($res->quantity_issued + $quantity) > $res->quantity_planned ? 'additional' : 'standard';

            $issue = ProductionOrderIssue::create([
                'tenant_id'           => $res->tenant_id,
                'production_order_id' => $res->production_order_id,
                'reservation_id'      => $res->id,
                'product_id'          => $res->product_id,
                'quantity_issued'     => $quantity,
                'issue_type'          => $type,
                'issued_by'           => $userId,
                'issued_at'           => now(),
                'remarks'             => $remarks,
            ]);

            $res->quantity_issued += $quantity;
            // Deduct from reserved as it's now consumed
            $res->quantity_reserved = max(0.0000, $res->quantity_reserved - $quantity);
            $res->save();

            return $issue;
        });
    }

    /**
     * Return unused material back to the inventory stock.
     */
    public function returnMaterial(int $reservationId, float $quantity, ?string $remarks = null, ?int $userId = null): ProductionOrderIssue
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException("Quantity to return must be greater than zero.");
        }

        return DB::transaction(function () use ($reservationId, $quantity, $remarks, $userId) {
            $res = ProductionOrderReservation::findOrFail($reservationId);

            if ($res->quantity_issued < $quantity) {
                throw new InvalidArgumentException("Cannot return more quantity than has been issued (Issued: {$res->quantity_issued}).");
            }

            $issue = ProductionOrderIssue::create([
                'tenant_id'           => $res->tenant_id,
                'production_order_id' => $res->production_order_id,
                'reservation_id'      => $res->id,
                'product_id'          => $res->product_id,
                'quantity_issued'     => -$quantity, // Negative represents a return
                'issue_type'          => 'return',
                'issued_by'           => $userId,
                'issued_at'           => now(),
                'remarks'             => $remarks,
            ]);

            $res->quantity_issued -= $quantity;
            $res->save();

            return $issue;
        });
    }
}
