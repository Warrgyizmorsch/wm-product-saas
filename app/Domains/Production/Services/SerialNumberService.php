<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionLotTrace;
use App\Domains\Production\Models\ProductionSerialNumber;
use Illuminate\Support\Facades\DB;

class SerialNumberService
{
    /**
     * Generate range of serial numbers.
     */
    public function generateSerials(
        int $tenantId,
        int $orderId,
        int $productId,
        int $quantity,
        string $prefix = 'SN',
        int $startNum = 1,
        ?int $batchId = null
    ): array {
        return DB::transaction(function () use ($tenantId, $orderId, $productId, $quantity, $prefix, $startNum, $batchId) {
            $serials = [];
            $currentNum = $startNum;

            for ($i = 0; $i < $quantity; $i++) {
                $numStr = $prefix . str_pad((string) $currentNum, 6, '0', STR_PAD_LEFT);

                // Collision avoidance
                while (ProductionSerialNumber::withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('serial_number', $numStr)
                    ->exists()
                ) {
                    $currentNum++;
                    $numStr = $prefix . str_pad((string) $currentNum, 6, '0', STR_PAD_LEFT);
                }

                $serial = ProductionSerialNumber::create([
                    'tenant_id'           => $tenantId,
                    'production_order_id' => $orderId,
                    'batch_id'            => $batchId,
                    'product_id'          => $productId,
                    'serial_number'       => $numStr,
                    'status'              => ProductionSerialNumber::STATUS_PLANNED,
                ]);

                // Traceability record
                ProductionLotTrace::create([
                    'tenant_id'   => $tenantId,
                    'source_type' => 'order',
                    'source_id'   => $orderId,
                    'target_type' => 'serial',
                    'target_id'   => $serial->id,
                    'quantity'    => 1.0000,
                    'remarks'     => 'Serial number registered.',
                ]);

                if ($batchId) {
                    ProductionLotTrace::create([
                        'tenant_id'   => $tenantId,
                        'source_type' => 'batch',
                        'source_id'   => $batchId,
                        'target_type' => 'serial',
                        'target_id'   => $serial->id,
                        'quantity'    => 1.0000,
                        'remarks'     => 'Serial linked to batch.',
                    ]);
                }

                $serials[] = $serial;
                $currentNum++;
            }

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id' => $orderId,
                'event_type'          => 'Serial Generated',
                'title'               => 'Serial Range Generated',
                'description'         => "Generated {$quantity} serial numbers with prefix '{$prefix}'.",
                'severity'            => 'info',
                'event_source'        => 'SerialNumberService',
            ]);

            return $serials;
        });
    }

    /**
     * Validate serial uniqueness.
     */
    public function validateUniqueness(string $serialNumber, int $tenantId): bool
    {
        return !ProductionSerialNumber::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('serial_number', $serialNumber)
            ->exists();
    }

    /**
     * Manually assign serial.
     */
    public function manualAssign(int $tenantId, int $orderId, int $productId, string $serialNumber, ?int $batchId = null): ProductionSerialNumber
    {
        if (!$this->validateUniqueness($serialNumber, $tenantId)) {
            throw new \LogicException("Serial number [{$serialNumber}] is already registered.");
        }

        return DB::transaction(function () use ($tenantId, $orderId, $productId, $serialNumber, $batchId) {
            $serial = ProductionSerialNumber::create([
                'tenant_id'           => $tenantId,
                'production_order_id' => $orderId,
                'batch_id'            => $batchId,
                'product_id'          => $productId,
                'serial_number'       => $serialNumber,
                'status'              => ProductionSerialNumber::STATUS_PLANNED,
            ]);

            ProductionLotTrace::create([
                'tenant_id'   => $tenantId,
                'source_type' => 'order',
                'source_id'   => $orderId,
                'target_type' => 'serial',
                'target_id'   => $serial->id,
                'quantity'    => 1.0000,
                'remarks'     => 'Manual serial registered.',
            ]);

            app(\App\Domains\Production\Services\ProductionEventService::class)->writeEvent($tenantId, [
                'production_order_id'         => $orderId,
                'production_serial_number_id' => $serial->id,
                'event_type'                  => 'Serial Generated',
                'title'                       => 'Serial Number Registered',
                'description'                 => "Manual serial number [{$serialNumber}] registered.",
                'severity'                    => 'info',
                'event_source'                => 'SerialNumberService',
            ]);

            return $serial;
        });
    }
}
