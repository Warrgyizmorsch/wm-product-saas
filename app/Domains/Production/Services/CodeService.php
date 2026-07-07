<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionScanLog;
use App\Domains\Production\Models\ProductionSerialNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CodeService
{
    /**
     * Generate barcode and QR code payloads for an entity and store them.
     */
    public function generate(Model $entity, string $type): Model
    {
        $prefix = match ($type) {
            'order'  => 'ORD',
            'batch'  => 'BAT',
            'serial' => 'SER',
            default  => 'GEN',
        };

        $code = "{$prefix}-" . str_pad((string)$entity->id, 8, '0', STR_PAD_LEFT);

        $entity->update([
            'barcode' => $code,
            'qr_code' => $code,
        ]);

        return $entity;
    }

    /**
     * Decode scan value into type and ID.
     */
    public function decode(string $codeString): array
    {
        $parts = explode('-', $codeString);
        if (count($parts) < 2) {
            throw new \InvalidArgumentException("Invalid code format: {$codeString}");
        }

        $prefix = $parts[0];
        $id = (int)$parts[1];

        $type = match ($prefix) {
            'ORD'   => 'order',
            'BAT'   => 'batch',
            'SER'   => 'serial',
            default => 'unknown',
        };

        return [
            'type' => $type,
            'id'   => $id,
        ];
    }

    /**
     * Validate code.
     */
    public function validate(string $codeString, int $tenantId): bool
    {
        try {
            $decoded = $this->decode($codeString);
            $type = $decoded['type'];
            $id   = $decoded['id'];

            return match ($type) {
                'order'  => ProductionOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('id', $id)->exists(),
                'batch'  => ProductionBatch::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('id', $id)->exists(),
                'serial' => ProductionSerialNumber::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('id', $id)->exists(),
                default  => false,
            };
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Resolve scanned entity and create scan log record.
     */
    public function resolveEntity(
        string $codeString,
        int $tenantId,
        int $userId,
        ?string $deviceIdentifier = null
    ): Model {
        $decoded = $this->decode($codeString);
        $type = $decoded['type'];
        $id   = $decoded['id'];

        $entity = match ($type) {
            'order'  => ProductionOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($id),
            'batch'  => ProductionBatch::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($id),
            'serial' => ProductionSerialNumber::withoutGlobalScopes()->where('tenant_id', $tenantId)->findOrFail($id),
            default  => throw new \LogicException("Unsupported entity type: {$type}"),
        };

        // Create log record
        ProductionScanLog::create([
            'tenant_id'         => $tenantId,
            'entity_type'       => $type,
            'entity_id'         => $id,
            'scan_type'         => $type,
            'scanned_by'        => $userId,
            'device_identifier' => $deviceIdentifier,
            'scanned_at'        => now(),
        ]);

        return $entity;
    }
}
