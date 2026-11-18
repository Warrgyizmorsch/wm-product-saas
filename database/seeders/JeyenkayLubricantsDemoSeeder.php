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
 *   - optional replay (JEYENKAY_REPLAY=1): one temp-flow production order per finished-good voucher, released and
 *     logged with the raw materials consumed under the same batch no
 *
 * NOT part of DatabaseSeeder / db:seed. Run on demand:
 *   php artisan db:seed --class=JeyenkayLubricantsDemoSeeder
 *   JEYENKAY_TENANT_ID=1 JEYENKAY_REPLAY=1 PRODUCTION_TEMP_FLOW=true php artisan db:seed --class=JeyenkayLubricantsDemoSeeder
 */
class JeyenkayLubricantsDemoSeeder extends Seeder
{
    public const FG_FILE = __DIR__ . '/data/jeyenkay_fg_manufactured.xlsx';
    public const RM_FILE = __DIR__ . '/data/jeyenkay_rm_consumed.xlsx';

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

        if (filter_var(env('JEYENKAY_REPLAY', false), FILTER_VALIDATE_BOOLEAN)) {
            $replayed = $this->replayVouchers($tenantId, $fgRows, $rmRows, $warehouseId);
            $this->command?->info("Replayed {$replayed} production voucher(s) as temp-flow orders.");
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

    // ─── replay ──────────────────────────────────────────────────────────────

    /**
     * One temp-flow order per finished-good voucher, released and logged once with the raw materials of the same batch no.
     * Skips vouchers already replayed (order description carries the voucher no).
     */
    public function replayVouchers(int $tenantId, array $fgRows, array $rmRows, int $warehouseId): int
    {
        config(['production.temp_flow' => true]);

        $userId = DB::table('users')->where('tenant_id', $tenantId)->orderBy('id')->value('id');
        $branchId = DB::table('warehouses')->where('id', $warehouseId)->value('branch_id');
        $companyId = DB::table('warehouses')->where('id', $warehouseId)->value('company_id');
        $rmByBatch = collect($rmRows)->groupBy('batch');
        $replayed = 0;

        foreach ($fgRows as $fg) {
            $marker = "Voucher {$fg['voucher']}";
            if (ProductionOrder::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('description', 'like', "%{$marker}%")->exists()) {
                continue;
            }

            $productId = DB::table('products')->where('tenant_id', $tenantId)->where('sku', self::sku($fg['name']))->value('id');
            if (! $productId) {
                continue;
            }

            $orderService = app(ProductionOrderService::class);
            $order = $orderService->createDirect([
                'product_id' => (int) $productId, 'quantity_ordered' => $fg['qty'],
                'start_date' => $fg['date'], 'end_date' => $fg['date'],
                'description' => "{$marker} / Batch {$fg['batch']}",
                'branch_id' => $branchId, 'company_id' => $companyId,
            ], $tenantId, $userId);
            $orderService->release($order->id, (int) $userId);

            $op = ProductionOrderOperation::where('production_order_id', $order->id)->orderBy('sequence')->first();
            $consumed = $rmByBatch->get($fg['batch'], collect())->map(function ($line) use ($tenantId, $warehouseId) {
                return [
                    'product_id' => DB::table('products')->where('tenant_id', $tenantId)->where('sku', self::sku($line['name']))->value('id'),
                    'quantity' => $line['qty'],
                    'warehouse_id' => $warehouseId,
                ];
            })->all();

            app(ProductionExecutionService::class)->logProgress(
                $op->id, $fg['qty'], 0.0, 0.0, 0.0, 0.0, "{$marker} / Batch {$fg['batch']}",
                null, $userId, true, 'jeyenkay-' . md5($fg['voucher']), null, $consumed
            );
            $replayed++;
        }

        return $replayed;
    }
}
