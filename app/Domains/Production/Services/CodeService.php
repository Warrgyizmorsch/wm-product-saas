<?php

namespace App\Domains\Production\Services;

use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Warehouse;
use App\Domains\Production\Models\Machine;
use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionScanLog;
use App\Domains\Production\Models\ProductionSerialNumber;
use App\Domains\Production\Models\WorkCenter;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CodeService
{
    /**
     * Barcode / QR payload formats.
     *
     * New labels use business identifiers so codes are meaningful without a database:
     *   PO:{order_number}       — Production Order
     *   LOT:{batch_number}      — Production Batch / Lot
     *   SN:{serial_number}      — Production Serial Number
     *   PRD:{sku}               — Product / SKU (not a lot — see correction #12)
     *   MCH:{machine_code}      — Machine
     *   WKC:{work_center_code}  — Work Centre
     *   WHS:{warehouse_code}    — Warehouse
     *   OPR:{employee_id}       — Operator (uses Employee.employee_id, not email)
     *
     * Legacy codes use internal IDs and are decoded for backward compatibility only:
     *   ORD-{id} | BAT-{id} | SER-{id}
     */

    // ─── Label Generation ────────────────────────────────────────────────────────

    /**
     * Encode a business-identifier label payload for a given entity.
     * Returns the canonical code string that should be stored in barcode / qr_code fields.
     *
     * @throws \InvalidArgumentException When the entity type is unsupported.
     */
    public function encodeLabel(Model $entity, string $type): string
    {
        return match ($type) {
            'order'      => 'PO:'   . $entity->order_number,
            'batch'      => 'LOT:'  . $entity->batch_number,
            'serial'     => 'SN:'   . $entity->serial_number,
            'product'    => 'PRD:'  . $entity->sku,
            'machine'    => 'MCH:'  . $entity->code,
            'work_center'=> 'WKC:'  . $entity->code,
            'warehouse'  => 'WHS:'  . $entity->code,
            'operator'   => 'OPR:'  . ($entity->employee_id ?? $entity->id),
            default      => throw new \InvalidArgumentException("Unsupported label entity type: {$type}"),
        };
    }

    /**
     * Generate and persist barcode / qr_code on the entity.
     * Uses business-identifier encoding for all new entity types.
     * Legacy ORD/BAT/SER internal-ID format preserved for backward compatibility
     * on existing entities that already have those codes stored.
     */
    public function generate(Model $entity, string $type): Model
    {
        $code = $this->encodeLabel($entity, $type);

        $entity->update([
            'barcode' => $code,
            'qr_code' => $code,
        ]);

        return $entity;
    }

    // ─── Decode ──────────────────────────────────────────────────────────────────

    /**
     * Decode a scan string into a structured array:
     *   ['type' => string, 'identifier' => string, 'format' => 'business'|'legacy']
     *
     * Supported formats:
     *   PO:{x}   LOT:{x}   SN:{x}   PRD:{x}   MCH:{x}   WKC:{x}   WHS:{x}   OPR:{x}
     *   ORD-{n}  BAT-{n}   SER-{n}  (legacy — decoded to type + numeric id)
     */
    public function decode(string $codeString): array
    {
        $code = trim($codeString);

        // ── Business-identifier format  e.g.  PO:WO-2026-000001 ─────────────
        if (str_contains($code, ':')) {
            [$prefix, $identifier] = explode(':', $code, 2);
            $type = match (strtoupper($prefix)) {
                'PO'  => 'order',
                'LOT' => 'batch',
                'SN'  => 'serial',
                'PRD' => 'product',
                'MCH' => 'machine',
                'WKC' => 'work_center',
                'WHS' => 'warehouse',
                'OPR' => 'operator',
                default => 'unknown',
            };

            return [
                'type'       => $type,
                'identifier' => $identifier,
                'format'     => 'business',
            ];
        }

        // ── Legacy format  e.g.  ORD-00000001 ────────────────────────────────
        $parts = explode('-', $code);
        if (count($parts) < 2) {
            throw new \InvalidArgumentException("Invalid barcode/QR code format: {$codeString}");
        }

        $prefix = strtoupper($parts[0]);
        $numericId = (int) $parts[1];

        $type = match ($prefix) {
            'ORD' => 'order',
            'BAT' => 'batch',
            'SER' => 'serial',
            default => 'unknown',
        };

        return [
            'type'       => $type,
            'identifier' => (string) $numericId, // numeric string for DB lookup
            'format'     => 'legacy',
        ];
    }

    // ─── Validation ──────────────────────────────────────────────────────────────

    /**
     * Validate that the scanned code resolves to an entity owned by the given tenant.
     */
    public function validate(string $codeString, int $tenantId): bool
    {
        try {
            $decoded = $this->decode($codeString);
            if ($decoded['type'] === 'unknown') {
                return false;
            }
            return $this->resolveModel($decoded, $tenantId) !== null;
        } catch (\Exception) {
            return false;
        }
    }

    // ─── Entity Resolution ───────────────────────────────────────────────────────

    /**
     * Resolve the scanned code to a model, log the scan, and return the model.
     *
     * @throws \LogicException|\InvalidArgumentException on unknown type or missing entity.
     */
    public function resolveEntity(
        string  $codeString,
        int     $tenantId,
        int     $userId,
        ?string $deviceIdentifier = null,
        string  $scanType      = 'view',
        string  $action        = 'view'
    ): Model {
        $decoded = $this->decode($codeString);

        if ($decoded['type'] === 'unknown') {
            // Log the failed scan then throw
            $this->writeScanLog([
                'tenant_id'         => $tenantId,
                'raw_code'          => $codeString,
                'entity_type'       => null,
                'entity_id'         => null,
                'scan_type'         => $scanType,
                'status'            => ProductionScanLog::STATUS_FAILED,
                'action_taken'      => $action,
                'failure_reason'    => "Unknown barcode prefix in: {$codeString}",
                'scanned_by'        => $userId,
                'device_identifier' => $deviceIdentifier,
                'scanned_at'        => now(),
            ]);
            throw new \InvalidArgumentException("Unknown barcode/QR code: {$codeString}");
        }

        $entity = $this->resolveModel($decoded, $tenantId);

        if ($entity === null) {
            $this->writeScanLog([
                'tenant_id'         => $tenantId,
                'raw_code'          => $codeString,
                'entity_type'       => $decoded['type'],
                'entity_id'         => null,
                'scan_type'         => $scanType,
                'status'            => ProductionScanLog::STATUS_FAILED,
                'action_taken'      => $action,
                'failure_reason'    => "Entity not found for tenant or does not exist.",
                'scanned_by'        => $userId,
                'device_identifier' => $deviceIdentifier,
                'scanned_at'        => now(),
            ]);
            throw new \LogicException("Entity not found or cross-tenant access denied for: {$codeString}");
        }

        $this->writeScanLog([
            'tenant_id'         => $tenantId,
            'raw_code'          => $codeString,
            'entity_type'       => $decoded['type'],
            'entity_id'         => $entity->id,
            'scan_type'         => $scanType,
            'status'            => ProductionScanLog::STATUS_SUCCESS,
            'action_taken'      => $action,
            'failure_reason'    => null,
            'scanned_by'        => $userId,
            'device_identifier' => $deviceIdentifier,
            'scanned_at'        => now(),
        ]);

        return $entity;
    }

    // ─── Private Helpers ─────────────────────────────────────────────────────────

    /**
     * Resolve the entity model from a decoded code array, scoped to tenant.
     * Returns null if not found (caller decides to throw or handle).
     */
    private function resolveModel(array $decoded, int $tenantId): ?Model
    {
        $type       = $decoded['type'];
        $identifier = $decoded['identifier'];
        $isLegacy   = ($decoded['format'] === 'legacy');

        return match ($type) {
            'order' => $isLegacy
                ? ProductionOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('id', (int)$identifier)->first()
                : ProductionOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('order_number', $identifier)->first(),

            'batch' => $isLegacy
                ? ProductionBatch::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('id', (int)$identifier)->first()
                : ProductionBatch::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('batch_number', $identifier)->first(),

            'serial' => $isLegacy
                ? ProductionSerialNumber::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('id', (int)$identifier)->first()
                : ProductionSerialNumber::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('serial_number', $identifier)->first(),

            'product' => Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sku', $identifier)->first(),

            'machine' => Machine::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $identifier)->first(),

            'work_center' => WorkCenter::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $identifier)->first(),

            'warehouse' => Warehouse::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('code', $identifier)->first(),

            'operator' => (function () use ($identifier, $tenantId) {
                $employee = \App\Domains\HRMS\Models\Employee::where('employee_id', $identifier)->first();
                if (!$employee && is_numeric($identifier)) {
                    $employee = \App\Domains\HRMS\Models\Employee::find((int)$identifier);
                }
                if (!$employee) {
                    return null;
                }
                return User::where('tenant_id', $tenantId)
                    ->where(function ($q) use ($employee) {
                        if ($employee->office_email) {
                            $q->where('email', $employee->office_email);
                        }
                        if ($employee->personal_email) {
                            $q->orWhere('email', $employee->personal_email);
                        }
                    })
                    ->first();
            })(),

            default => null,
        };
    }

    /**
     * Persist a scan log entry.
     */
    private function writeScanLog(array $attributes): void
    {
        ProductionScanLog::create($attributes);
    }
}
