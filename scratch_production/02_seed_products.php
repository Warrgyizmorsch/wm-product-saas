<?php
/**
 * 02_seed_products.php
 *
 * Creates 38 Products for Tenant 1 THROUGH THE INVENTORY WEB FORM (inventory.products.store), as a user viewing in INR:
 * - 25 Raw Materials (test-raw-01..25)            — supplier method "Buy" (trade)
 * - 8 Semi-Finished Components (test-semi-01..08) — supplier method "Manufacture"
 * - 3 Semi-Final Sub-Assemblies (test-semifinal-01..03)
 * - 2 Finished Goods (test-final-01..02)
 *
 * Cost / selling price and the opening-stock unit cost are typed in ₹; the Inventory service converts them to base
 * and records the opening stock (warehouse_stocks[warehouse_id][quantity|unit_cost]) like the form does.
 * Uses the existing UOMs (Mtr, Kg, Pcs, Set), warehouses (WH-RM-TBL, WH-WIP-TBL, WH-FG-TBL) and accounts
 * (4010 sales, 5010 purchase, 1200 inventory). A product whose SKU already exists is skipped (opening stock is
 * a one-time entry), so run 00_reset first for a clean run.
 *
 * Usage: php scratch_production/02_seed_products.php
 */

require __DIR__ . '/_http.php';

use App\Domains\Inventory\Models\Product;
use Illuminate\Support\Facades\DB;

$http = scratch_http();
$tenantId = SCRATCH_TENANT_ID;
$failures = 0;

scratch_header('SEEDING 38 PRODUCTS & OPENING STOCK');

// ── 1. Existing masters the form offers ───────────────────────────────────────
$uoms = DB::table('uoms')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
$warehouses = DB::table('warehouses')->where('tenant_id', $tenantId)->pluck('id', 'code')->all();
$accounts = DB::table('chart_of_accounts')->where('tenant_id', $tenantId)->whereIn('code', ['1200', '4010', '5010'])->pluck('id', 'code')->all();

foreach (['Mtr', 'Kg', 'Pcs', 'Set'] as $c) {
    if (!isset($uoms[$c])) { echo "Missing UOM {$c} — create it in Inventory first." . PHP_EOL; exit(1); }
}
foreach (['WH-RM-TBL', 'WH-WIP-TBL', 'WH-FG-TBL'] as $c) {
    if (!isset($warehouses[$c])) { echo "Missing warehouse {$c} — create it in Inventory first." . PHP_EOL; exit(1); }
}

// ── 2. Product Catalog (money in ₹) ───────────────────────────────────────────
$productsData = [
    // 25 Raw Materials
    ['sku' => 'test-raw-01', 'name' => 'test-raw-01', 'uom' => 'Mtr', 'qty' => 2500, 'cost' => 80.00,  'hsn' => '73066100', 'desc' => 'Structural steel square tubing 40x40mm', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-02', 'name' => 'test-raw-02', 'uom' => 'Mtr', 'qty' => 1500, 'cost' => 150.00, 'hsn' => '76042990', 'desc' => 'Anodized aluminum extrusion guide rail', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-03', 'name' => 'test-raw-03', 'uom' => 'Mtr', 'qty' => 1000, 'cost' => 220.00, 'hsn' => '74112100', 'desc' => 'Seamless industrial copper piping 15mm', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-04', 'name' => 'test-raw-04', 'uom' => 'Mtr', 'qty' => 3000, 'cost' => 25.00,  'hsn' => '40169320', 'desc' => 'EPDM rubber sealing gasket strip', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-05', 'name' => 'test-raw-05', 'uom' => 'Mtr', 'qty' => 5000, 'cost' => 15.00,  'hsn' => '72171010', 'desc' => 'High-tensile galvanized steel wire', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-06', 'name' => 'test-raw-06', 'uom' => 'Mtr', 'qty' => 2000, 'cost' => 30.00,  'hsn' => '39172300', 'desc' => 'Rigid PVC protective cable conduit', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-07', 'name' => 'test-raw-07', 'uom' => 'Kg',  'qty' => 1200, 'cost' => 180.00, 'hsn' => '39079900', 'desc' => 'Electrostatic matte black polyester powder', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-08', 'name' => 'test-raw-08', 'uom' => 'Kg',  'qty' => 800,  'cost' => 240.00, 'hsn' => '39073010', 'desc' => 'Industrial epoxy primer base resin', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-09', 'name' => 'test-raw-09', 'uom' => 'Kg',  'qty' => 600,  'cost' => 320.00, 'hsn' => '38249900', 'desc' => 'High-performance chemical hardener', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-10', 'name' => 'test-raw-10', 'uom' => 'Kg',  'qty' => 2000, 'cost' => 60.00,  'hsn' => '72051010', 'desc' => 'Chilled iron shot blasting grit', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-11', 'name' => 'test-raw-11', 'uom' => 'Kg',  'qty' => 1000, 'cost' => 110.00, 'hsn' => '27101981', 'desc' => 'Soluble synthetic cutting and cooling oil', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-12', 'name' => 'test-raw-12', 'uom' => 'Kg',  'qty' => 1500, 'cost' => 140.00, 'hsn' => '72171030', 'desc' => 'Copper-coated MIG welding wire ER70S-6', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-13', 'name' => 'test-raw-13', 'uom' => 'Kg',  'qty' => 500,  'cost' => 280.00, 'hsn' => '32089090', 'desc' => 'Polyurethane surface protective sealant', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-14', 'name' => 'test-raw-14', 'uom' => 'Pcs', 'qty' => 800,  'cost' => 450.00, 'hsn' => '72107000', 'desc' => 'Cold-rolled CRCA steel sheet 2mm', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-15', 'name' => 'test-raw-15', 'uom' => 'Pcs', 'qty' => 600,  'cost' => 650.00, 'hsn' => '44111400', 'desc' => 'High-density moisture-resistant wood panel', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-16', 'name' => 'test-raw-16', 'uom' => 'Pcs', 'qty' => 2500, 'cost' => 40.00,  'hsn' => '73269099', 'desc' => 'Heavy stamped steel mounting flange', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-17', 'name' => 'test-raw-17', 'uom' => 'Pcs', 'qty' => 10000,'cost' => 5.00,   'hsn' => '73181500', 'desc' => 'Hex head cap screw bolt M8x40 Grade 8.8', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-18', 'name' => 'test-raw-18', 'uom' => 'Pcs', 'qty' => 10000,'cost' => 3.00,   'hsn' => '73181600', 'desc' => 'Nylon insert lock nut M8 zinc plated', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-19', 'name' => 'test-raw-19', 'uom' => 'Pcs', 'qty' => 15000,'cost' => 2.00,   'hsn' => '73182200', 'desc' => 'Flat washer M8 heavy duty DIN 125A', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-20', 'name' => 'test-raw-20', 'uom' => 'Pcs', 'qty' => 4000, 'cost' => 15.00,  'hsn' => '40169990', 'desc' => 'Anti-vibration rubber base foot pad', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-21', 'name' => 'test-raw-21', 'uom' => 'Pcs', 'qty' => 1500, 'cost' => 65.00,  'hsn' => '48191010', 'desc' => 'Double-wall heavy corrugated outer carton', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-22', 'name' => 'test-raw-22', 'uom' => 'Pcs', 'qty' => 500,  'cost' => 120.00, 'hsn' => '39232990', 'desc' => 'Protective multi-layer bubble wrap roll', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-23', 'name' => 'test-raw-23', 'uom' => 'Set', 'qty' => 1200, 'cost' => 75.00,  'hsn' => '73181900', 'desc' => 'Complete mounting hardware fastener set', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-24', 'name' => 'test-raw-24', 'uom' => 'Set', 'qty' => 800,  'cost' => 160.00, 'hsn' => '85444299', 'desc' => 'Internal electrical wiring harness kit', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],
    ['sku' => 'test-raw-25', 'name' => 'test-raw-25', 'uom' => 'Set', 'qty' => 400,  'cost' => 350.00, 'hsn' => '38101010', 'desc' => 'Surface 3-stage chemical pre-wash kit', 'wh' => 'WH-RM-TBL', 'type' => 'raw_material', 'plan' => 'purchase'],

    // 8 Semi-Finished Components
    ['sku' => 'test-semi-01', 'name' => 'test-semi-01', 'uom' => 'Pcs', 'qty' => 600, 'cost' => 180.00, 'hsn' => '73066100', 'desc' => 'Precision cut and deburred steel leg column', 'wh' => 'WH-WIP-TBL', 'type' => 'semi_finished', 'plan' => 'manufacture'],
    ['sku' => 'test-semi-02', 'name' => 'test-semi-02', 'uom' => 'Pcs', 'qty' => 800, 'cost' => 120.00, 'hsn' => '73269099', 'desc' => 'CNC press-brake bent bracket', 'wh' => 'WH-WIP-TBL', 'type' => 'semi_finished', 'plan' => 'manufacture'],
    ['sku' => 'test-semi-03', 'name' => 'test-semi-03', 'uom' => 'Pcs', 'qty' => 500, 'cost' => 220.00, 'hsn' => '84833000', 'desc' => 'Machined mounting flange plate with threads', 'wh' => 'WH-WIP-TBL', 'type' => 'semi_finished', 'plan' => 'manufacture'],
    ['sku' => 'test-semi-04', 'name' => 'test-semi-04', 'uom' => 'Pcs', 'qty' => 400, 'cost' => 850.00, 'hsn' => '44111400', 'desc' => 'Routed and chamfered engineered top panel', 'wh' => 'WH-WIP-TBL', 'type' => 'semi_finished', 'plan' => 'manufacture'],
    ['sku' => 'test-semi-05', 'name' => 'test-semi-05', 'uom' => 'Pcs', 'qty' => 450, 'cost' => 320.00, 'hsn' => '73269099', 'desc' => 'TIG welded corner reinforcing brace leg', 'wh' => 'WH-WIP-TBL', 'type' => 'semi_finished', 'plan' => 'manufacture'],
    ['sku' => 'test-semi-06', 'name' => 'test-semi-06', 'uom' => 'Pcs', 'qty' => 600, 'cost' => 190.00, 'hsn' => '73269099', 'desc' => 'Powder-coated horizontal support stiffener', 'wh' => 'WH-WIP-TBL', 'type' => 'semi_finished', 'plan' => 'manufacture'],
    ['sku' => 'test-semi-07', 'name' => 'test-semi-07', 'uom' => 'Mtr', 'qty' => 500, 'cost' => 260.00, 'hsn' => '76042990', 'desc' => 'Surface-treated & coated aluminum profile', 'wh' => 'WH-WIP-TBL', 'type' => 'semi_finished', 'plan' => 'manufacture'],
    ['sku' => 'test-semi-08', 'name' => 'test-semi-08', 'uom' => 'Set', 'qty' => 500, 'cost' => 110.00, 'hsn' => '73181900', 'desc' => 'Pre-assembled hardware installation set', 'wh' => 'WH-WIP-TBL', 'type' => 'semi_finished', 'plan' => 'manufacture'],

    // 3 Semi-Final Sub-Assemblies
    ['sku' => 'test-semifinal-01', 'name' => 'test-semifinal-01', 'uom' => 'Pcs', 'qty' => 150, 'cost' => 1400.00, 'hsn' => '94039000', 'desc' => 'Fully welded structural steel base chassis', 'wh' => 'WH-WIP-TBL', 'type' => 'semi_finished', 'plan' => 'manufacture'],
    ['sku' => 'test-semifinal-02', 'name' => 'test-semifinal-02', 'uom' => 'Pcs', 'qty' => 120, 'cost' => 1950.00, 'hsn' => '94039000', 'desc' => 'Finished powder-coated table frame assembly', 'wh' => 'WH-WIP-TBL', 'type' => 'semi_finished', 'plan' => 'manufacture'],
    ['sku' => 'test-semifinal-03', 'name' => 'test-semifinal-03', 'uom' => 'Pcs', 'qty' => 100, 'cost' => 1200.00, 'hsn' => '94039000', 'desc' => 'Sealed & treated top board with inserts', 'wh' => 'WH-WIP-TBL', 'type' => 'semi_finished', 'plan' => 'manufacture'],

    // 2 Finished Goods
    ['sku' => 'test-final-01', 'name' => 'test-final-01', 'uom' => 'Pcs', 'qty' => 40, 'cost' => 3500.00, 'selling' => 6200.00, 'hsn' => '94032000', 'desc' => 'Executive Office Dining / Conference Table (1800x900)', 'wh' => 'WH-FG-TBL', 'type' => 'finished_good', 'plan' => 'manufacture'],
    ['sku' => 'test-final-02', 'name' => 'test-final-02', 'uom' => 'Pcs', 'qty' => 25, 'cost' => 4800.00, 'selling' => 8500.00, 'hsn' => '94032000', 'desc' => 'Premium Industrial Heavy Conference Table (2400x1000)', 'wh' => 'WH-FG-TBL', 'type' => 'finished_good', 'plan' => 'manufacture'],
];

echo "Creating 38 Products..." . PHP_EOL;

foreach ($productsData as $p) {
    if (Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sku', $p['sku'])->exists()) {
        scratch_line('SKIP', "{$p['sku']} already exists");
        continue;
    }

    $whId = $warehouses[$p['wh']];
    $payload = [
        'name'                     => $p['name'],
        'item_type'                => 'Goods',
        'variation_type'           => 'Single',
        'supplier_method'          => $p['plan'] === 'manufacture' ? 'manufacture' : 'trade',
        'type'                     => $p['type'],
        'sku'                      => $p['sku'],
        'uom_id'                   => $uoms[$p['uom']],
        'hsn_sac'                  => $p['hsn'],
        'gst_rate'                 => 18,
        'selling_price'            => $p['selling'] ?? 0,
        'cost_price'               => $p['cost'],
        'sales_account'            => $accounts['4010'] ?? '4010',
        'purchase_account'         => $accounts['5010'] ?? '5010',
        'inventory_account'        => $accounts['1200'] ?? '1200',
        'reorder_point'            => 10,
        'inventory_valuation_method' => 'FIFO',
        'default_production_model' => 'pure_manufacturing',
        'description'              => $p['desc'],
        'warehouse_stocks'         => [$whId => ['quantity' => $p['qty'], 'unit_cost' => $p['cost']]],
    ];

    $r = $http->send('POST', route('inventory.products.store'), $payload);
    $saved = Product::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('sku', $p['sku'])->first();
    $r->ok || $failures++;
    scratch_line('CREATE', "{$p['sku']} ({$p['qty']} {$p['uom']} @ ₹{$p['cost']} in {$p['wh']}) -> "
        . ($r->ok && $saved ? "ID {$saved->id}, stored cost base " . format_decimal((float) $saved->cost_price) : $r->summary()));
}

echo PHP_EOL . ($failures ? "Finished with {$failures} failure(s)." : '38 Products and opening stock seeded successfully!') . PHP_EOL;
exit($failures ? 1 : 0);
