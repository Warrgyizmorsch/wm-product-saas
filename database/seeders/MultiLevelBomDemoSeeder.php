<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Uom;
use App\Domains\Production\Models\ProductionBom;
use App\Domains\Production\Models\ProductionBomItem;
use App\Domains\Production\Models\ProductionBomApproval;
use App\Domains\Production\Models\Routing;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class MultiLevelBomDemoSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Get PCS and LTR UOMs
            $pcs = Uom::where('tenant_id', $tenant->id)->where('code', 'PCS')->first();
            $ltr = Uom::where('tenant_id', $tenant->id)->where('code', 'LTR')->first();
            $kg = Uom::where('tenant_id', $tenant->id)->where('code', 'KG')->first();

            if (!$pcs || !$ltr || !$kg) {
                continue;
            }

            // Find the child product "Car Door Assembly" (FG-CAR-DOOR)
            $carDoor = Product::where('tenant_id', $tenant->id)->where('sku', 'FG-CAR-DOOR')->first();
            if (!$carDoor) {
                continue;
            }

            // Create parent product: "Standard Sedan Car"
            $car = Product::updateOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => 'FG-SEDAN-CAR'],
                [
                    'name' => 'Standard Sedan Car',
                    'type' => 'finished_good',
                    'status' => 'active',
                    'unit_cost' => 0.0000,
                ]
            );

            // Create a routing reference
            $routing = Routing::where('tenant_id', $tenant->id)->first();
            $routingId = $routing ? $routing->id : null;

            // Create BOM for Standard Sedan Car
            $user = User::where('tenant_id', $tenant->id)->first();
            $userId = $user ? $user->id : null;

            $bom = ProductionBom::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'bom_number' => 'BOM-SEDAN-CAR-001',
                    'version' => '1.0.0',
                ],
                [
                    'bom_name' => 'Standard Sedan Car Assembly BOM',
                    'bom_type' => 'manufacturing',
                    'product_id' => $car->id,
                    'base_quantity' => 1.0000, // to build 1 Sedan Car
                    'base_uom_id' => $pcs->id,
                    'revision' => 0,
                    'revision_reason' => 'Initial multi-level launch.',
                    'routing_id' => $routingId,
                    'effective_date' => Carbon::now()->toDateString(),
                    'expiry_date' => Carbon::now()->addYears(2)->toDateString(),
                    'status' => 'approved', // Active & Approved
                    'notes' => 'Top-level Bill of Materials for Standard Sedan Car (Multi-level Demo)',
                    'created_by' => $userId,
                    'approved_by' => $userId,
                    'approved_at' => Carbon::now(),
                ]
            );

            // Item 1: Car Door Assembly (4 PCS required per car)
            ProductionBomItem::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'bom_id' => $bom->id,
                    'material_id' => $carDoor->id,
                ],
                [
                    'quantity' => 4.0000, // 4 doors per car
                    'uom_id' => $pcs->id,
                    'material_scrap_percentage' => 0.00,
                    'is_alternative' => false,
                    'sequence' => 1,
                    'notes' => 'Sub-assembly child BOM'
                ]
            );

            // Item 2: Red Paint (10 Liters required for final chassis paint job)
            $redPaint = Product::where('tenant_id', $tenant->id)->where('sku', 'RM-PAINT-RED')->first();
            if ($redPaint) {
                ProductionBomItem::updateOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'bom_id' => $bom->id,
                        'material_id' => $redPaint->id,
                    ],
                    [
                        'quantity' => 10.0000,
                        'uom_id' => $ltr->id,
                        'material_scrap_percentage' => 2.00, // 2% paint loss during spray
                        'is_alternative' => false,
                        'sequence' => 2,
                        'notes' => 'Chassis paint coating'
                    ]
                );
            }

            // Seed Approval History for parent BOM
            ProductionBomApproval::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'bom_id' => $bom->id,
                    'action' => 'Created',
                ],
                [
                    'user_id' => $userId,
                    'comments' => 'Created sedan car parent BOM.'
                ]
            );

            ProductionBomApproval::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'bom_id' => $bom->id,
                    'action' => 'Approved',
                ],
                [
                    'user_id' => $userId,
                    'comments' => 'Approved sedan car parent BOM.'
                ]
            );
        }
    }
}
