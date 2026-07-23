<?php

namespace App\Domains\Production\Services;

use App\Domains\Production\Models\ProductionScrapDisposal;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ScrapService handles NCR-linked scrap disposal approval workflow.
 *
 * ── Inventory Stock Posting ─────────────────────────────────────────────────────
 * This service does NOT post to inventory. Inventory stock outflows for scrapped
 * production output are posted exclusively by ProductionExecutionService::logScrap().
 *
 * The distinction between the three scrap concepts is:
 *
 *  (a) Production material consumption  → ProductionMaterialService::issueMaterial()
 *      Raw materials consumed by the production process.
 *
 *  (b) Production scrap stock removal   → ProductionExecutionService::logScrap()
 *      Defective finished/semi-finished goods removed from production inventory.
 *      Stock outflow is posted here — with idempotency guard via stock_transaction_id.
 *
 *  (c) NCR quality disposal approval    → ScrapService::approveDisposal()
 *      Formal quality-management approval of a scrap disposal decision linked to an NCR.
 *      This updates the disposal record status and timestamps only.
 *      It does NOT post to inventory again — the stock was already removed in step (b).
 */
class ScrapService
{
    /**
     * Create a scrap disposal record (linked to a quality NCR).
     */
    public function createScrapDisposal(int $tenantId, array $data): ProductionScrapDisposal
    {
        return DB::transaction(function () use ($tenantId, $data) {
            return ProductionScrapDisposal::create([
                'tenant_id'   => $tenantId,
                'ncr_id'      => $data['ncr_id'] ?? null,
                'category'    => $data['category'],
                'reason_code' => $data['reason_code'],
                'quantity'    => $data['quantity'],
                'cost'        => $data['cost'] ?? 0.00,
                'status'      => 'pending_approval',
            ]);
        });
    }

    /**
     * Approve and complete the disposal workflow.
     *
     * Only approval-workflow fields (status, disposed_at, disposed_by) are updated.
     * NO inventory posting occurs here — that was done by ProductionExecutionService::logScrap()
     * when the scrap was first recorded. Calling StockService here would cause a
     * double stock deduction.
     */
    public function approveDisposal(int $disposalId, int $userId, ?int $tenantId = null): void
    {
        DB::transaction(function () use ($disposalId, $userId, $tenantId) {
            $disposal = ProductionScrapDisposal::query()
                ->when($tenantId !== null, fn ($query) => $query->where('tenant_id', $tenantId))
                ->findOrFail($disposalId);

            if ($disposal->status !== 'pending_approval') {
                throw new \InvalidArgumentException('Only pending scrap disposals can be approved.');
            }

            $disposal->update([
                'status'      => 'approved',
                'disposed_at' => Carbon::now(),
                'disposed_by' => $userId,
            ]);

            // Auto-resolve NCR
            $ncr = $disposal->ncr;
            if ($ncr && $ncr->status !== 'closed') {
                $ncr->update([
                    'status' => 'closed',
                    'closed_by' => $userId,
                    'closed_at' => Carbon::now(),
                    'esignature_closed' => hash('sha256', ($userId ?? 'system').$ncr->id.'closed'.now()->timestamp),
                ]);

                // Write a timeline event for the order
                app(ProductionEventService::class)->writeEvent($ncr->tenant_id, [
                    'production_order_id' => $ncr->production_order_id,
                    'event_type' => 'NCR Closed',
                    'title' => 'Non-Conformance Resolved',
                    'description' => "NCR {$ncr->ncr_number} automatically closed upon Scrap Disposal approval.",
                    'severity' => 'success',
                    'event_source' => 'ScrapService',
                    'triggered_by' => $userId,
                ]);
            }
        });
    }
}
