<?php

namespace App\Domains\Production\Repositories;

use App\Domains\Production\Models\ProductionQualityInspection;
use App\Domains\Production\Models\ProductionNcr;
use App\Domains\Production\Models\ProductionCapa;
use App\Domains\Production\Models\ProductionReworkOrder;
use App\Domains\Production\Models\ProductionScrapDisposal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductionQualityRepository implements ProductionQualityRepositoryInterface
{
    public function paginateInspections(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionQualityInspection::with(['order.product', 'auditor', 'plan']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where('inspection_number', 'like', $search);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function findInspection(int $id): ?ProductionQualityInspection
    {
        return ProductionQualityInspection::with(['order.product', 'plan.parameters', 'results.parameter', 'auditor'])->find($id);
    }

    public function createInspection(array $data): ProductionQualityInspection
    {
        return ProductionQualityInspection::create($data);
    }

    public function updateInspection(int $id, array $data): ProductionQualityInspection
    {
        $inspection = ProductionQualityInspection::findOrFail($id);
        $inspection->update($data);
        return $inspection->fresh();
    }

    public function paginateNcrs(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionNcr::with(['order.product', 'operator']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function findNcr(int $id): ?ProductionNcr
    {
        return ProductionNcr::with(['order.product', 'inspection', 'scrapDisposal', 'reworkOrder'])->find($id);
    }

    public function createNcr(array $data): ProductionNcr
    {
        return ProductionNcr::create($data);
    }

    public function paginateCapas(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionCapa::with(['ncr.order', 'owner']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function createCapa(array $data): ProductionCapa
    {
        return ProductionCapa::create($data);
    }

    public function paginateReworkOrders(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionReworkOrder::with(['originalOrder.product', 'ncr']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function createReworkOrder(array $data): ProductionReworkOrder
    {
        return ProductionReworkOrder::create($data);
    }

    public function paginateScrapDisposals(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = ProductionScrapDisposal::with(['ncr.order.product', 'disposer']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }

    public function createScrapDisposal(array $data): ProductionScrapDisposal
    {
        return ProductionScrapDisposal::create($data);
    }

    public function findReworkOrder(int $id): ?ProductionReworkOrder
    {
        return ProductionReworkOrder::with(['originalOrder.product', 'ncr', 'operations.workCenter', 'operations.machine'])->find($id);
    }

    public function findScrapDisposal(int $id): ?ProductionScrapDisposal
    {
        return ProductionScrapDisposal::with(['ncr.order.product', 'disposer'])->find($id);
    }

    public function findCapa(int $id): ?ProductionCapa
    {
        return ProductionCapa::with(['ncr.order', 'owner'])->find($id);
    }

    public function getQualityDashboardKpis(int $tenantId): array
    {
        $totalFinalInspections = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->where('stage', 'final')
            ->count();

        $passedFinalInspections = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->where('stage', 'final')
            ->where('result', 'passed')
            ->count();

        $fpy = $totalFinalInspections > 0 ? ($passedFinalInspections / $totalFinalInspections) * 100 : 100.00;

        $totalInspections = ProductionQualityInspection::where('tenant_id', $tenantId)->count();
        $pendingInspections = ProductionQualityInspection::where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->whereNull('result')
                  ->orWhereIn('status', ['pending', 'in_progress', 'draft']);
            })
            ->count();
        $passedInspections = ProductionQualityInspection::where('tenant_id', $tenantId)->where('result', 'passed')->count();
        $failedInspections = ProductionQualityInspection::where('tenant_id', $tenantId)->where('result', 'failed')->count();

        $scrapCount = ProductionScrapDisposal::where('tenant_id', $tenantId)->count();
        $reworkCount = ProductionReworkOrder::where('tenant_id', $tenantId)->count();

        $ncrOpen = ProductionNcr::where('tenant_id', $tenantId)->where('status', 'open')->count();
        $ncrClosed = ProductionNcr::where('tenant_id', $tenantId)->where('status', 'closed')->count();

        $capaOpen = ProductionCapa::where('tenant_id', $tenantId)->whereIn('status', ['draft', 'active'])->count();
        $capaClosed = ProductionCapa::where('tenant_id', $tenantId)->where('status', 'closed')->count();

        $recentInspections = ProductionQualityInspection::with(['plan', 'order.product'])
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        $recentNcrs = ProductionNcr::with(['order.product', 'operator'])
            ->where('tenant_id', $tenantId)
            ->orderBy('id', 'desc')
            ->take(5)
            ->get();

        return compact(
            'fpy',
            'totalInspections',
            'pendingInspections',
            'passedInspections',
            'failedInspections',
            'scrapCount',
            'reworkCount',
            'ncrOpen',
            'ncrClosed',
            'capaOpen',
            'capaClosed',
            'recentInspections',
            'recentNcrs'
        );
    }
}
