<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionBatch;
use App\Domains\Production\Models\ProductionOperatorAssignment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class ProductionBatchRepository implements ProductionBatchRepositoryInterface
{
    public function paginateBatches(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionBatch::with(['order.product', 'currentOperation']);

        if (!empty($filters['production_order_id'])) {
            $query->where('production_order_id', $filters['production_order_id']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where('batch_number', 'like', $search);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function find(int $id): ?ProductionBatch
    {
        return ProductionBatch::with([
            'order.product',
            'currentOperation',
            'serials',
            'parentGenealogies',
            'childGenealogies',
        ])->find($id);
    }

    public function findBatchByNumber(string $batchNumber): ?ProductionBatch
    {
        return ProductionBatch::where('batch_no', $batchNumber)->first();
    }

    public function getBatchesForOperation(int $operationId): Collection
    {
        return ProductionBatch::where('current_operation_id', $operationId)
            ->orderBy('id')
            ->get();
    }

    public function lockBatchForUpdate(int $batchId): ProductionBatch
    {
        return ProductionBatch::where('id', $batchId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    public function create(array $data): ProductionBatch
    {
        return ProductionBatch::create($data);
    }

    public function update(int $id, array $data): ProductionBatch
    {
        $batch = ProductionBatch::findOrFail($id);
        $batch->update($data);
        return $batch->fresh();
    }

    public function delete(int $id): bool
    {
        $batch = ProductionBatch::findOrFail($id);
        return (bool) $batch->delete();
    }

    public function getActiveOperatorAssignments(int $operatorId): Collection
    {
        return ProductionOperatorAssignment::where('user_id', $operatorId)
            ->whereIn('status', ['assigned', 'active', 'in_progress'])
            ->with(['batch', 'operation', 'workCenter', 'machine'])
            ->get();
    }
}
