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

class ProductionBomSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            // Seed UOMs
            $kg = Uom::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'KG'],
                ['name' => 'Kilogram']
            );

            $ltr = Uom::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'LTR'],
                ['name' => 'Litre']
            );

            $pcs = Uom::updateOrCreate(
                ['tenant_id' => $tenant->id, 'code' => 'PCS'],
                ['name' => 'Pieces']
            );

            // Seed Products with Unit Costs
            $carDoor = Product::updateOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => 'FG-CAR-DOOR'],
                [
                    'name' => 'Car Door Assembly',
                    'type' => 'finished_good',
                    'status' => 'active',
                    'unit_cost' => 0.0000,
                ]
            );

            $steelSheet = Product::updateOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => 'RM-STEEL-SHEET'],
                [
                    'name' => 'Steel Sheet',
                    'type' => 'raw_material',
                    'status' => 'active',
                    'unit_cost' => 10.0000, // $10 per KG
                ]
            );

            $paint = Product::updateOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => 'RM-PAINT-RED'],
                [
                    'name' => 'Red Paint',
                    'type' => 'raw_material',
                    'status' => 'active',
                    'unit_cost' => 15.0000, // $15 per LTR
                ]
            );

            $bolt = Product::updateOrCreate(
                ['tenant_id' => $tenant->id, 'sku' => 'RM-BOLT-M8'],
                [
                    'name' => 'M8 Bolt',
                    'type' => 'raw_material',
                    'status' => 'active',
                    'unit_cost' => 0.5000, // $0.50 per PCS
                ]
            );

            // Seed a sample Routing
            $routing = Routing::updateOrCreate(
                ['tenant_id' => $tenant->id, 'name' => 'Standard Assembly Line Routing'],
                ['status' => 'active']
            );

            // Seed a sample BOM
            $user = User::where('tenant_id', $tenant->id)->first();
            $userId = $user ? $user->id : null;

            $bom = ProductionBom::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'bom_number' => 'BOM-CAR-DOOR-001',
                    'version' => '1.0.0',
                ],
                [
                    'bom_name' => 'Car Door Assembly Standard BOM',
                    'bom_type' => 'manufacturing',
                    'product_id' => $carDoor->id,
                    'base_quantity' => 100.0000, // to build 100 doors
                    'base_uom_id' => $pcs->id,
                    'revision' => 0,
                    'revision_reason' => 'Initial release.',
                    'routing_id' => $routing->id,
                    'effective_date' => Carbon::now()->toDateString(),
                    'expiry_date' => Carbon::now()->addYears(2)->toDateString(),
                    'status' => 'approved', // Seed as active/approved
                    'notes' => 'Standard Bill of Materials for Car Door Assembly Red Edition',
                    'created_by' => $userId,
                    'approved_by' => $userId,
                    'approved_at' => Carbon::now(),
                ]
            );

            // Steel Sheet item
            ProductionBomItem::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'bom_id' => $bom->id,
                    'material_id' => $steelSheet->id,
                ],
                [
                    'quantity' => 200.0000, // 200kg steel needed for 100 doors
                    'uom_id' => $kg->id,
                    'material_scrap_percentage' => 5.00,
                    'is_alternative' => false,
                    'sequence' => 1,
                    'notes' => 'Grade A steel sheets'
                ]
            );

            // Paint item
            ProductionBomItem::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'bom_id' => $bom->id,
                    'material_id' => $paint->id,
                ],
                [
                    'quantity' => 50.0000, // 50 litres red paint for 100 doors
                    'uom_id' => $ltr->id,
                    'material_scrap_percentage' => 10.00,
                    'is_alternative' => false,
                    'sequence' => 2,
                    'notes' => 'Gloss paint red'
                ]
            );

            // Bolt item
            ProductionBomItem::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'bom_id' => $bom->id,
                    'material_id' => $bolt->id,
                ],
                [
                    'quantity' => 1000.0000, // 1000 bolts for 100 doors
                    'uom_id' => $pcs->id,
                    'material_scrap_percentage' => 0.00,
                    'is_alternative' => false,
                    'sequence' => 3,
                    'notes' => 'Rust-proof bolts'
                ]
            );

            // Seed Approval History
            ProductionBomApproval::updateOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'bom_id' => $bom->id,
                    'action' => 'Created',
                ],
                [
                    'user_id' => $userId,
                    'comments' => 'Initial seed creation.'
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
                    'comments' => 'Approved during seeding.'
                ]
            );
        }
    }
}
