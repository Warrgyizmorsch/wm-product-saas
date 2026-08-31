<?php

namespace App\Domains\HRMS\Listeners;

use App\Domains\HRMS\Models\Asset;
use App\Domains\HRMS\Models\AssetCategory;
use App\Domains\HRMS\Models\AssetItem;
use App\Domains\Purchase\Events\GrnAssetLineReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-creates unallocated HRMS Asset units when a GRN line marked as a
 * capital purchase (line_type=asset) is received — physical custody starts
 * at receipt, independent of when the Vendor Bill is later posted to the GL.
 * Fails soft: an HRMS setup gap (missing category) must never block the GRN.
 */
class CreateAssetFromGrnLine
{
    public function handle(GrnAssetLineReceived $event): void
    {
        $item = $event->item;

        if (Asset::where('goods_receipt_note_item_id', $item->id)->exists()) {
            return;
        }

        try {
            $category = AssetCategory::find($item->asset_category_id);

            if (!$category) {
                Log::warning('CreateAssetFromGrnLine: missing asset_category_id, skipping auto-creation', [
                    'grn_item_id' => $item->id,
                ]);
                return;
            }

            $name = $item->product?->name ?? $item->remarks ?? "GRN Line #{$item->id}";
            $unitCount = max(1, (int) round($item->accepted_qty));
            $unitCost = $unitCount > 0 ? round($item->total_amount / $unitCount, 2) : $item->total_amount;

            DB::transaction(function () use ($item, $category, $name, $unitCount, $unitCost) {
                $assetItem = AssetItem::firstOrCreate(
                    ['asset_category_id' => $category->id, 'name' => $name],
                    ['company_id' => $category->company_id, 'description' => 'Auto-created from Purchase GRN receipts.']
                );

                for ($i = 1; $i <= $unitCount; $i++) {
                    Asset::create([
                        'company_id' => $category->company_id,
                        'asset_category_id' => $category->id,
                        'asset_item_id' => $assetItem->id,
                        'goods_receipt_note_item_id' => $item->id,
                        'asset_code' => "AST-{$item->id}-{$i}",
                        'name' => $name,
                        'purchase_date' => $item->goodsReceiptNote?->received_date,
                        'purchase_cost' => $unitCost,
                        'condition' => 'new',
                        'status' => 'available',
                        'notes' => "Auto-created from GRN #{$item->goods_receipt_note_id}, line #{$item->id}.",
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Log::warning('CreateAssetFromGrnLine: failed to auto-create asset', [
                'grn_item_id' => $item->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
