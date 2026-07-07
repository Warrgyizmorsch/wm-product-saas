<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionEventTimeline;
use Illuminate\Support\Facades\Auth;

class ProductionEventService
{
    /**
     * Publish a centralized manufacturing event.
     */
    public function writeEvent(int $tenantId, array $data): ProductionEventTimeline
    {
        return ProductionEventTimeline::create([
            'tenant_id'                      => $tenantId,
            'production_order_id'            => $data['production_order_id'] ?? null,
            'production_order_operation_id'  => $data['production_order_operation_id'] ?? null,
            'production_batch_id'            => $data['production_batch_id'] ?? null,
            'production_serial_number_id'    => $data['production_serial_number_id'] ?? null,
            'machine_id'                     => $data['machine_id'] ?? null,
            'operator_id'                    => $data['operator_id'] ?? null,
            'event_type'                     => $data['event_type'],
            'title'                          => $data['title'],
            'description'                    => $data['description'],
            'severity'                       => $data['severity'] ?? 'info',
            'event_source'                   => $data['event_source'] ?? 'System',
            'event_time'                     => $data['event_time'] ?? now(),
            'triggered_by'                   => $data['triggered_by'] ?? Auth::id(),
            'metadata'                       => $data['metadata'] ?? null,
        ]);
    }
}
