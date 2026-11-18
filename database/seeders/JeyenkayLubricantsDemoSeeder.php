<?php

namespace Database\Seeders;

use App\Domains\Inventory\Services\StockService;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionOrderService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Jeyenkay lubricants demo data for the temp no-BOM production flow.
 *
 * Reads two reference sheets (database/seeders/data/):
 *   jeyenkay_fg_manufactured.xlsx  — Daily Production Report: voucher, finished good, qty, unit, batch no
 *   jeyenkay_rm_consumed.xlsx      — Daily Consumption Report: voucher, raw material, type, qty, unit, same batch no
 *
 * Creates (never updates / deletes anything that already exists; safe to re-run):
 *   - units of measure ltr / kgs
 *   - finished goods (manufacture) and raw / semi-finished materials (buy), batch tracked
 *   - one warehouse JEY-WH when the tenant has none, and opening stock per raw material
 *     (consumed total in the sheet + 25 %), as a single FIFO lot per material
 *   - the routing "Test-jan" (Heating -> Bleading) — an existing one is reused as it is
 *   - a replay of selected vouchers (REPLAY_VOUCHERS) as temp-flow production orders on Test-jan: released, logged on
 *     both operations with the raw materials of the same batch no consumed, finished goods received into stock under
 *     that batch no. JEYENKAY_REPLAY=0 skips it, JEYENKAY_REPLAY=all replays every voucher of the sheet.
 *
 * NOT part of DatabaseSeeder / db:seed. Run on demand:
 *   php artisan db:seed --class=JeyenkayLubricantsDemoSeeder
 *   JEYENKAY_TENANT_ID=1 JEYENKAY_REPLAY=0 php artisan db:seed --class=JeyenkayLubricantsDemoSeeder
 */
class JeyenkayLubricantsDemoSeeder extends Seeder
{
    public const FG_FILE = __DIR__ . '/data/jeyenkay_fg_manufactured.xlsx';
    public const RM_FILE = __DIR__ . '/data/jeyenkay_rm_consumed.xlsx';

    /**
     * Vouchers replayed by default. Each becomes a production order whose order number IS the sheet's voucher number.
     *  JNK ZIRCON-12 (4 orders, 4 different material mixes):
     *    OP/2627/0000345  BASE OIL AP 70N + HP MILJUTE SUPER N 12
     *    OP/2627/0000353  VELVEX WHITE TECHNICAL + BASE OIL AP 70N + HP MILJUTE SUPER N 12
     *    OP/2627/0000365  BASE OIL AP 70N + WHITE OIL LIGHT
     *    OP/2627/0000371  BASE OIL AP 70N + VELVEX WHITE TECHNICAL 60 + WHITE OIL A-20
     *  JNK AMBER OIL - 32 (same materials, different quantities):
     *    OP/2627/0000344  RUBESS 32 65,000 + HP MILJUTE 32 4,990
     *    OP/2627/0000351  RUBESS 32 57,840 + HP MILJUTE 32 2,330
     *  JNK ZIRCON - ASCO D2 (output in kgs, inputs mix kgs and litres):
     *    OP/2627/0000367  FIBROL SVL 700 3kg + FIBROL SVL 250 3kg + HP WITEOL 15 370ltr + BASE OIL AP 70N 556ltr + EMUL WHITE 78kg
     */
    public const REPLAY_VOUCHERS = [
        'OP/2627/0000345', 'OP/2627/0000353', 'OP/2627/0000365', 'OP/2627/0000371',
        'OP/2627/0000344', 'OP/2627/0000351',
        'OP/2627/0000367',
    ];

    /** Vouchers whose first operation is logged in two shifts (none by default). */
    public const PARTIAL_SHIFT_VOUCHERS = [];

    /** null = decide from JEYENKAY_REPLAY (default on; "0" turns it off, "all" replays every voucher). */
    public ?bool $replay = null;

    public function run(?int $tenantId = null): void
    {
        $tenantId ??= (int) env('JEYENKAY_TENANT_ID', 1);

        [$fgRows, $rmRows] = [self::readFinishedGoods(), self::readRawMaterials()];
        if ($fgRows === [] || $rmRows === []) {
            $this->command?->error('Reference sheets are missing or empty under database/seeders/data/.');

            return;
        }

        $uomIds = $this->seedUnits($tenantId, $fgRows, $rmRows);
        $warehouseId = $this->ensureWarehouse($tenantId);

        $fgCount = $this->seedFinishedGoods($tenantId, $fgRows, $uomIds);
        $rmCount = $this->seedRawMaterials($tenantId, $rmRows, $uomIds);
        $stockCount = $this->seedOpeningStock($tenantId, $rmRows, $warehouseId);

        $this->command?->info("Jeyenkay demo (tenant {$tenantId}): {$fgCount} finished goods, {$rmCount} raw materials created; {$stockCount} opening stock lots posted.");

        $mode = strtolower((string) env('JEYENKAY_REPLAY', '1'));
        $replay = $this->replay ?? ! in_array($mode, ['0', 'false', 'off', 'no'], true);
        if ($replay) {
            $routingId = $this->ensureTestJanRouting($tenantId);
            $rows = $mode === 'all' ? $fgRows : array_values(array_filter($fgRows, fn ($r) => in_array($r['voucher'], self::REPLAY_VOUCHERS, true)));
            $replayed = $this->replayVouchers($tenantId, $rows, $rmRows, $warehouseId, $routingId);
            $this->command?->info("Replayed {$replayed} production voucher(s) as temp-flow orders on routing Test-jan (#{$routingId}).");
        }
    }

    // ─── sheets ──────────────────────────────────────────────────────────────

    /** @return array<int, array{voucher:string, date:string, name:string, qty:float, unit:string, batch:string}> */
    public static function readFinishedGoods(string $file = self::FG_FILE): array
    {
        $rows = [];
        foreach (self::sheetRows($file) as $r) {
            if (! is_numeric($r[1] ?? null)) {
                continue;
            }
            [$voucher, $date] = self::splitVoucher((string) $r[2]);
            $rows[] = [
                'voucher' => $voucher, 'date' => $date,
                'name' => trim((string) $r[3]), 'qty' => (float) $r[6], 'unit' => strtolower(trim((string) $r[7])),
                'batch' => trim((string) $r[8]),
            ];
        }

        return $rows;
    }

    /** @return array<int, array{voucher:string, date:string, name:string, type:string, qty:float, unit:string, batch:string}> */
    public static function readRawMaterials(string $file = self::RM_FILE): array
    {
        $rows = [];
        foreach (self::sheetRows($file) as $r) {
            if (! is_numeric($r[1] ?? null)) {
                continue;
            }
            [$voucher, $date] = self::splitVoucher((string) $r[2]);
            $rows[] = [
                'voucher' => $voucher, 'date' => $date,
                'name' => trim((string) $r[3]), 'type' => trim((string) $r[4]), 'qty' => (float) $r[5],
                'unit' => strtolower(trim((string) $r[6])), 'batch' => trim((string) $r[7]),
            ];
        }

        return $rows;
    }

    private static function sheetRows(string $file): array
    {
        if (! is_file($file)) {
            return [];
        }

        return IOFactory::load($file)->getActiveSheet()->toArray(null, true, true, false);
    }

    /** "OP/2627/0000343 21/10/2026" -> ["OP/2627/0000343", "2026-10-21"] */
    private static function splitVoucher(string $cell): array
    {
        $cell = trim($cell);
        if (preg_match('#^(\S+)\s+(\d{2})/(\d{2})/(\d{4})$#', $cell, $m)) {
            return [$m[1], "{$m[4]}-{$m[3]}-{$m[2]}"];
        }

        return [$cell, now()->toDateString()];
    }

    private static function sku(string $name): string
    {
        return strtoupper(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
    }

    // ─── masters ─────────────────────────────────────────────────────────────

    /** @return array<string,int> unit code => uoms.id */
    private function seedUnits(int $tenantId, array $fgRows, array $rmRows): array
    {
        $labels = ['ltr' => 'Litre', 'kgs' => 'Kilogram'];
        $codes = collect($fgRows)->pluck('unit')->merge(collect($rmRows)->pluck('unit'))->unique()->filter()->values();
        $hasCategory = Schema::hasColumn('uoms', 'category');
        $ids = [];

        foreach ($codes as $code) {
            $existing = DB::table('uoms')->where('tenant_id', $tenantId)->where('code', $code)->value('id');
            if (! $existing) {
                $existing = DB::table('uoms')->insertGetId([
                    'tenant_id' => $tenantId, 'name' => $labels[$code] ?? strtoupper($code), 'code' => $code,
                    'created_at' => now(), 'updated_at' => now(),
                ] + ($hasCategory ? ['category' => 'Goods'] : []));
            }
            $ids[$code] = (int) $existing;
        }

        return $ids;
    }

    private function ensureWarehouse(int $tenantId): int
    {
        $existing = DB::table('warehouses')->where('tenant_id', $tenantId)->where('status', 'active')
            ->orderByDesc('is_default')->orderBy('id')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $values = ['tenant_id' => $tenantId, 'name' => 'Jeyenkay Main Warehouse', 'code' => 'JEY-WH', 'status' => 'active', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()];
        if (Schema::hasColumn('warehouses', 'type')) {
            $values['type'] = 'internal';
        }

        return (int) DB::table('warehouses')->insertGetId(array_intersect_key($values, array_flip(Schema::getColumnListing('warehouses'))));
    }

    private function insertProduct(int $tenantId, string $name, string $type, string $supplierMethod, ?int $uomId): bool
    {
        $sku = self::sku($name);
        if (DB::table('products')->where('tenant_id', $tenantId)->where(fn ($q) => $q->where('sku', $sku)->orWhere('name', $name))->exists()) {
            return false;
        }

        $values = [
            'tenant_id' => $tenantId, 'name' => $name, 'sku' => $sku, 'type' => $type, 'item_type' => 'Goods',
            'variation_type' => 'Single', 'uom_id' => $uomId, 'status' => 'active', 'supplier_method' => $supplierMethod,
            'default_production_model' => 'pure_manufacturing', 'inventory_valuation_method' => 'FIFO',
            'track_batch' => true, 'track_serial_number' => false,
            'unit_cost' => 0, 'cost_price' => 0, 'selling_price' => 0, 'opening_stock' => 0, 'opening_stock_rate' => 0,
            'created_at' => now(), 'updated_at' => now(),
        ];
        DB::table('products')->insert(array_intersect_key($values, array_flip(Schema::getColumnListing('products'))));

        return true;
    }

    private function seedFinishedGoods(int $tenantId, array $fgRows, array $uomIds): int
    {
        $created = 0;
        foreach (collect($fgRows)->unique('name') as $row) {
            $created += (int) $this->insertProduct($tenantId, $row['name'], 'finished_good', 'manufacture', $uomIds[$row['unit']] ?? null);
        }

        return $created;
    }

    private function seedRawMaterials(int $tenantId, array $rmRows, array $uomIds): int
    {
        $created = 0;
        foreach (collect($rmRows)->unique('name') as $row) {
            $type = stripos($row['type'], 'semi') !== false ? 'semi_finished' : 'raw_material';
            $created += (int) $this->insertProduct($tenantId, $row['name'], $type, 'buy', $uomIds[$row['unit']] ?? null);
        }

        return $created;
    }

    /** One opening FIFO lot per raw material: consumed total in the sheet + 25 %. Skipped when it already has one. */
    private function seedOpeningStock(int $tenantId, array $rmRows, int $warehouseId): int
    {
        $posted = 0;
        foreach (collect($rmRows)->groupBy('name') as $name => $lines) {
            $productId = DB::table('products')->where('tenant_id', $tenantId)->where('sku', self::sku($name))->value('id');
            if (! $productId) {
                continue;
            }

            $hasLot = DB::table('stock_transactions')->where('tenant_id', $tenantId)->where('product_id', $productId)
                ->where('warehouse_id', $warehouseId)->where('reference_type', 'Opening Stock')->exists();
            if ($hasLot) {
                continue;
            }

            $qty = ceil($lines->sum('qty') * 1.25);
            StockService::recordInflow($tenantId, (int) $productId, $warehouseId, $qty, 0.0, 'Opening Stock', (int) $productId, 'OPEN-' . self::sku($name));
            $posted++;
        }

        return $posted;
    }

    // ─── test-jan routing + replayed production orders ───────────────────────

    /**
     * The "Test-jan" routing (Heating -> Bleading). An existing routing of that name is reused untouched; otherwise it is
     * created with the same two operations and, when found by name, the same work centers.
     */
    public function ensureTestJanRouting(int $tenantId): int
    {
        $existing = DB::table('routings')->where('tenant_id', $tenantId)->where('name', 'Test-jan')->orderByDesc('id')->value('id');
        if ($existing) {
            return (int) $existing;
        }

        $routingId = (int) DB::table('routings')->insertGetId([
            'tenant_id' => $tenantId, 'routing_number' => 'RTG-TESTJAN', 'name' => 'Test-jan', 'version' => '1.0.0',
            'status' => 'active', 'effective_from' => now()->toDateString(), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $wc = fn (string $like) => DB::table('production_work_centers')->where('tenant_id', $tenantId)->where('name', 'like', $like)->value('id');
        $heating = DB::table('production_routing_operations')->insertGetId($this->routingOp($tenantId, $routingId, 10, 'Heating', $wc('Sub-Assembly%'), null));
        DB::table('production_routing_operations')->insert($this->routingOp($tenantId, $routingId, 20, 'Bleading', $wc('Roll Converting%'), $heating));

        return $routingId;
    }

    private function routingOp(int $tenantId, int $routingId, int $sequence, string $name, $workCenterId, ?int $previousId): array
    {
        $values = [
            'tenant_id' => $tenantId, 'routing_id' => $routingId, 'sequence' => $sequence,
            'operation_number' => 'OP-' . str_pad((string) $sequence, 3, '0', STR_PAD_LEFT), 'name' => $name,
            'operation_type' => 'manufacturing', 'work_center_id' => $workCenterId ?: null, 'previous_operation_id' => $previousId,
            'setup_time_minutes' => 0, 'processing_time_minutes' => 0, 'expected_yield_percentage' => 100, 'cost_per_unit' => 0,
            'is_external' => false, 'created_at' => now(), 'updated_at' => now(),
        ];

        return array_intersect_key($values, array_flip(Schema::getColumnListing('production_routing_operations')));
    }

    /**
     * Replays finished-good vouchers as temp-flow production orders on the Test-jan routing (Heating -> Bleading):
     * order -> release -> shop floor logs on each operation with the raw materials of the same batch no consumed
     * (the first half of the batch's materials at Heating, the rest at Bleading) -> finished goods received into
     * stock under the sheet's batch no. Partial-shift vouchers log Heating twice (60 % / 40 %).
     * Skips vouchers already replayed (order description carries the voucher no). Times are illustrative.
     *
     * @return int number of orders created
     */
    public function replayVouchers(int $tenantId, array $fgRows, array $rmRows, int $warehouseId, ?int $routingId = null): int
    {
        config(['production.temp_flow' => true]);

        $routingId ??= $this->ensureTestJanRouting($tenantId);
        $userId = DB::table('users')->where('tenant_id', $tenantId)->orderBy('id')->value('id');
        $branchId = DB::table('warehouses')->where('id', $warehouseId)->value('branch_id');
        $companyId = DB::table('warehouses')->where('id', $warehouseId)->value('company_id');
        $rmByBatch = collect($rmRows)->groupBy('batch');
        $replayed = 0;

        foreach ($fgRows as $fg) {
            $marker = "Voucher {$fg['voucher']}";
            if (ProductionOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('order_number', $fg['voucher'])->exists()) {
                continue;
            }

            $productId = DB::table('products')->where('tenant_id', $tenantId)->where('sku', self::sku($fg['name']))->value('id');
            if (! $productId) {
                continue;
            }

            $orderService = app(ProductionOrderService::class);
            $order = $orderService->createDirect([
                'order_number' => $fg['voucher'], 'product_id' => (int) $productId, 'quantity_ordered' => $fg['qty'], 'routing_id' => $routingId,
                'start_date' => $fg['date'], 'end_date' => $fg['date'],
                'description' => "{$marker} / Batch {$fg['batch']}",
                'branch_id' => $branchId, 'company_id' => $companyId,
            ], $tenantId, $userId);
            $orderService->release($order->id, (int) $userId);

            $ops = ProductionOrderOperation::where('production_order_id', $order->id)->orderBy('sequence')->get();
            $lines = $rmByBatch->get($fg['batch'], collect())->map(fn ($l) => [
                'product_id' => DB::table('products')->where('tenant_id', $tenantId)->where('sku', self::sku($l['name']))->value('id'),
                'quantity' => $l['qty'], 'warehouse_id' => $warehouseId,
            ])->values();

            // materials per operation: first half at the first operation, the rest at the last one
            $firstCount = $ops->count() > 1 ? (int) ceil($lines->count() / 2) : $lines->count();
            $perOp = [$ops->first()->id => $lines->take($firstCount)->all()];
            if ($ops->count() > 1) {
                $perOp[$ops->last()->id] = $lines->slice($firstCount)->values()->all();
            }

            $partial = in_array($fg['voucher'], self::PARTIAL_SHIFT_VOUCHERS, true);
            $logIds = [];
            foreach ($ops as $i => $op) {
                $opLines = $perOp[$op->id] ?? [];
                $runMinutes = $i === 0 ? 180.0 : 90.0;
                $setupMinutes = $i === 0 ? 15.0 : 10.0;

                $slices = ($partial && $i === 0) ? [0.6, 0.4] : [1.0];
                $producedSoFar = 0.0;
                $consumedSoFar = [];
                foreach ($slices as $s => $share) {
                    $final = $s === count($slices) - 1;
                    $qty = $final ? round($fg['qty'] - $producedSoFar, 4) : round($fg['qty'] * $share, 4);
                    $producedSoFar += $qty;

                    $sliceLines = [];
                    foreach ($opLines as $k => $line) {
                        $q = $final ? round($line['quantity'] - ($consumedSoFar[$k] ?? 0.0), 4) : round($line['quantity'] * $share, 4);
                        $consumedSoFar[$k] = ($consumedSoFar[$k] ?? 0.0) + $q;
                        $sliceLines[] = ['quantity' => $q] + $line;
                    }

                    $minutes = count($slices) > 1 ? round($runMinutes * $share, 2) : $runMinutes;
                    $log = app(ProductionExecutionService::class)->logProgress(
                        $op->id, $qty, 0.0, 0.0, $s === 0 ? $setupMinutes : 0.0, $minutes,
                        "{$marker} / Batch {$fg['batch']}" . (count($slices) > 1 ? ' (shift ' . ($s + 1) . ' of ' . count($slices) . ')' : ''),
                        null, $userId, $final, 'jeyenkay-' . md5($fg['voucher'] . $op->id . $s), null, $sliceLines
                    );
                    $logIds[] = $log->id;
                }
            }

            // sheet date on the logs and consumption rows (they are created "now")
            foreach ($logIds as $n => $logId) {
                $at = $fg['date'] . ' ' . sprintf('%02d:00:00', 8 + $n * 3);
                DB::table('production_order_progress_logs')->where('id', $logId)->update(['recorded_at' => $at]);
                DB::table('production_order_issues')->where('production_order_id', $order->id)->where('remarks', 'like', "%shop floor log #{$logId} %")->update(['issued_at' => $at]);
            }

            // finished goods into stock under the sheet's batch no (skipped with a warning if the order cannot be received yet)
            try {
                app(ProductionExecutionService::class)->receiveFinishedGoods($order->id, $fg['qty'], 'passed', "{$marker} / Batch {$fg['batch']}", $userId, $warehouseId, $fg['batch']);
            } catch (\Throwable $e) {
                $this->command?->warn("Finished goods of {$fg['voucher']} were not received into stock: " . $e->getMessage());
            }

            // all operations are done and the output is received: close the order like the order page would
            try {
                app(ProductionOrderService::class)->evaluateAndAutoCompleteOrder($order->id, $userId);
            } catch (\Throwable $e) {
                $this->command?->warn("Order {$fg['voucher']} could not be completed automatically: " . $e->getMessage());
            }

            $replayed++;
        }

        return $replayed;
    }
}
