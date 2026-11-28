<?php

namespace Database\Seeders;

use App\Core\Branch\BranchContext;
use App\Core\Company\CompanyContext;
use App\Core\Tenant\TenantRunner;
use App\Domains\HRMS\Models\Branch;
use App\Domains\HRMS\Models\Company;
use App\Domains\Production\Models\DeliveryChallan;
use App\Domains\Production\Models\ProductionOrder;
use App\Domains\Production\Models\ProductionOrderOperation;
use App\Domains\Production\Models\ProductionQualityPlanParameter;
use App\Domains\Production\Models\ProductionSchedule;
use App\Domains\Production\Models\ProductionScheduleOperation;
use App\Domains\Production\Models\ProductionSubcontractReceipt;
use App\Domains\Production\Repositories\DeliveryChallanRepositoryInterface;
use App\Domains\Production\Services\FinalQualityGateService;
use App\Domains\Production\Services\MesExecutionService;
use App\Domains\Production\Services\OrderOperationQualityService;
use App\Domains\Production\Services\ProductionExecutionService;
use App\Domains\Production\Services\ProductionOrderService;
use App\Domains\Production\Services\QualityInspectionService;
use App\Domains\Production\Services\SchedulingService;
use App\Domains\Production\Services\SubcontractMaterialBalanceService;
use App\Domains\Production\Services\SubcontractReceiptService;
use App\Domains\Purchase\Controllers\VendorBillController;
use App\Domains\Sales\Services\MaterialRequestService;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\Concerns\LoadsTestProductionData;
use Database\Seeders\Support\TestProductionLinks;
use Illuminate\Database\Seeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Production orders at every stage, made the way a user makes them (docs/production_seeder_audit_2026-11-26.md
 * Part D, user 2026-11-28: "a few mid stages ready production order … and few complete production orders … and all the
 * data related to those"). Every step goes through the service the screen calls — create, material request to the
 * store, store reserve + issue, release + schedule, MES start / log, the operation inspection page (one record = one
 * quantity with one result, QD), dispatch slip, subcontract receipt (GRN), Service Bill "Job Work", WIP final QC,
 * finished-goods receipt — so every related row (reservations, slips, stock ledger, WIP, events, journals) is real.
 *
 * Runs after TestProductionStockSeeder (stock must exist). Each order is one transaction: if a step fails, that order
 * is rolled back and the seeder stops with the order's name and the step.
 */
class TestProductionOrderSeeder extends Seeder
{
    use LoadsTestProductionData;

    private int $tenantId;
    private TestProductionLinks $links;
    private int $userId;
    private int $branchId;

    /** @var array<int, string> one line per order for the console */
    private array $summary = [];

    public function run(?int $tenantId = null): void
    {
        $this->tenantId = $this->resolveTenantId($tenantId);
        $this->links = new TestProductionLinks($this->tenantId);
        $this->userId = $this->links->userId(TestProductionLinks::SEED_USER_EMAIL);
        $this->branchId = $this->links->branchId('HQ');
        $tenant = Tenant::findOrFail($this->tenantId);

        app(TenantRunner::class)->run($tenant, function () {
            // the session a tester has: company, branch HQ (where the test stock is), logged in as the seed user
            app(CompanyContext::class)->set(Company::withoutGlobalScopes()->findOrFail($this->links->companyId()));
            app(BranchContext::class)->set(Branch::withoutGlobalScopes()->findOrFail($this->branchId));
            Auth::setUser(User::findOrFail($this->userId));

            foreach ($this->scenarios() as $label => $scenario) {
                try {
                    $order = DB::transaction(fn () => $scenario());
                } catch (\Throwable $e) {
                    throw new RuntimeException("TestProductionOrderSeeder — {$label}: {$e->getMessage()}", 0, $e);
                }
                $order->refresh();
                $this->summary[] = sprintf('%-48s %s  %s', $label, $order->order_number, $order->status);
            }
        });

        $this->command?->info('TestProductionOrderSeeder: ' . count($this->summary) . ' production order(s):');
        foreach ($this->summary as $line) {
            $this->command?->line('  ' . $line);
        }
    }

    /**
     * The orders, oldest stage last. Quantities stay inside the opening stock, so no semi is made inside these orders.
     *
     * @return array<string, \Closure(): ProductionOrder>
     */
    private function scenarios(): array
    {
        return [
            // ── Completed ─────────────────────────────────────────────────────────────────────────────────────
            'Completed · in-house, QC in parts (bracket)' => function () {
                // routing 002: laser blank (no QC) → press-brake bend (QC) → deburr (QC); QC done in two records each
                $order = $this->createOrder('test-semi-02', 20, -12, 'Seeded: completed in-house order, QC recorded in parts.');
                $this->requestAndIssueAll($order);
                $this->schedule($order, -11);
                $this->runInHouse($order, 10, [[20]]);
                $this->runInHouse($order, 20, [[12], [8]]);
                $this->runInHouse($order, 40, [[10], [10]]);
                $this->finish($order);

                return $order;
            },
            'Completed · two job works with GRN + bills (gusset)' => function () {
                // routing 010: laser job work (CRCA sheet sent) → in-house deburr → zinc job work (deburred plates
                // sent, the plater also uses its own chemicals); both job works have QC on receipt
                $order = $this->createOrder('test-semi-09', 40, -20, 'Seeded: completed order with two job works, receipts, GRNs and vendor bills.');
                $this->requestAndIssueAll($order);
                $this->schedule($order, -19);
                $laser = $this->op($order, 10);
                $slip = $this->dispatchSlip($laser, 40, -18);
                $this->receive($laser, 40, $slip, true);
                $this->inspect($laser, 40, true);
                $this->vendorBill($laser, 18.0 * 40, 'LASER-INV-1041');
                $this->runInHouse($order, 20, [[40]]);
                $zinc = $this->op($order, 30);
                $slip = $this->dispatchSlip($zinc, 40, -10);
                $this->receive($zinc, 25, $slip, false);
                $this->receive($zinc, 15, $slip, true);
                $this->inspect($zinc, 25, true);
                $this->inspect($zinc, 15, true);
                $this->vendorBill($zinc, 12.0 * 40, 'ZINC-INV-2208');
                $this->finish($order);

                return $order;
            },
            'Completed · vendor-made, no dispatch slip (rail)' => function () {
                // routing 011: the vendor makes the rail from its own material — nothing sent, received directly
                $order = $this->createOrder('test-semi-10', 20, -15, 'Seeded: completed vendor-made order (no dispatch slip), GRN + vendor bill.');
                $this->schedule($order, -14);
                $rail = $this->op($order, 10);
                $this->receive($rail, 20, null, true);
                $this->inspect($rail, 20, true);
                $this->vendorBill($rail, 240.0 * 20, 'COAT-INV-0907');
                $this->finish($order);

                return $order;
            },

            // ── Mid stages ────────────────────────────────────────────────────────────────────────────────────
            'Stage 1 · draft (nothing requested)' => function () {
                return $this->createOrder('test-final-01', 10, 3, 'Seeded: draft order — edit, request material or schedule it.');
            },
            'Stage 2 · material requested, not issued' => function () {
                $order = $this->createOrder('test-semi-06', 30, 2, 'Seeded: material request sent to the store, nothing issued yet.');
                $this->requestAll($order);

                return $order;
            },
            'Stage 3 · issued + released, not scheduled' => function () {
                $order = $this->createOrder('test-semi-01', 50, 1, 'Seeded: material issued and order released — schedule it next.');
                $this->requestAndIssueAll($order);
                app(ProductionOrderService::class)->release($order->id, $this->userId, true);

                return $order;
            },
            'Stage 4 · scheduled, not started' => function () {
                $order = $this->createOrder('test-semi-04', 20, 0, 'Seeded: scheduled and released to the shop floor, not started.');
                $this->requestAndIssueAll($order);
                $this->schedule($order, 0);

                return $order;
            },
            'Stage 5 · running, units waiting for QC' => function () {
                // op 010 done (no QC); op 020 logged 20 — they wait in its open inspection record
                $order = $this->createOrder('test-semi-02', 30, -2, 'Seeded: running — 20 units of the bending operation wait for QC.');
                $this->requestAndIssueAll($order);
                $this->schedule($order, -2);
                $this->runInHouse($order, 10, [[30]]);
                $this->runInHouse($order, 20, [], 20);

                return $order;
            },
            'Stage 6 · QC in parts with a failed record' => function () {
                // op 010 cut 40: 30 passed in one record, 10 failed in another (NCR, Rework / Scrap). The cut stays open
                // (10 more good units needed), so the deburr operation cannot start yet
                $order = $this->createOrder('test-semi-01', 40, -3, 'Seeded: QC in parts — 30 passed, 10 failed (separate records, NCR); cutting still open.');
                $this->requestAndIssueAll($order);
                $this->schedule($order, -3);
                $this->runInHouse($order, 10, [[30], [10, false]]);

                return $order;
            },
            'Stage 7 · job work at the vendor' => function () {
                $order = $this->createOrder('test-semi-09', 40, -4, 'Seeded: CRCA sheet dispatched to the laser cutter — at the vendor.');
                $this->requestAndIssueAll($order);
                $this->schedule($order, -4);
                $this->dispatchSlip($this->op($order, 10), 40, -3);

                return $order;
            },
            'Stage 8 · received from vendor, waiting for QC' => function () {
                $order = $this->createOrder('test-semi-09', 24, -6, 'Seeded: 24 laser-cut plates received — waiting for QC (no GRN yet).');
                $this->requestAndIssueAll($order);
                $this->schedule($order, -6);
                $laser = $this->op($order, 10);
                $slip = $this->dispatchSlip($laser, 24, -5);
                $this->receive($laser, 24, $slip, true);

                return $order;
            },
            'Stage 9 · vendor-made, waiting for the receipt' => function () {
                $order = $this->createOrder('test-semi-10', 10, -1, 'Seeded: vendor-made rail scheduled — nothing received yet (no dispatch slip needed).');
                $this->schedule($order, -1);

                return $order;
            },
        ];
    }

    // ─── Steps (each the service its screen calls) ──────────────────────────────────────────────────────────────

    /** Production → Orders → Add (createDirect): the product's approved BOM and its routing, dates from today. */
    private function createOrder(string $sku, float $qty, int $startDays, string $description): ProductionOrder
    {
        $start = now()->addDays($startDays)->startOfDay();

        return app(ProductionOrderService::class)->createDirect([
            'product_id' => $this->links->productId($sku),
            'quantity_ordered' => $qty,
            'start_date' => $start->toDateString(),
            'end_date' => $start->copy()->addDays(10)->toDateString(),
            'company_id' => $this->links->companyId(),
            'branch_id' => $this->branchId,
            'description' => $description,
        ], $this->tenantId, $this->userId);
    }

    /** Order → Materials → Send request: one request with every line still to request. */
    private function requestAll(ProductionOrder $order): ?\App\Domains\Production\Models\ProductionRequisitionSlip
    {
        $service = app(ProductionOrderService::class);
        $items = $service->materialRequestLines($order->fresh())
            ->filter(fn ($line) => $line['remaining'] > 0)
            ->map(fn ($line) => ['product_id' => $line['product_id'], 'quantity' => $line['remaining']])
            ->values()
            ->all();
        if ($items === []) {
            return null;
        }

        return $service->sendMaterialRequest($order->fresh(), $items, $this->userId, 'Seeded material request');
    }

    /** …then the store (Material Requests screen) reserves and issues every line from the warehouse that holds it. */
    private function requestAndIssueAll(ProductionOrder $order): void
    {
        $slip = $this->requestAll($order);
        if (!$slip) {
            return;
        }
        $store = app(MaterialRequestService::class);
        foreach ($slip->items()->get() as $item) {
            $qty = (float) $item->quantity_planned;
            if ($qty <= 0) {
                continue;
            }
            $warehouseId = $this->warehouseWithStock((int) $item->product_id, $qty);
            $store->reserve($this->tenantId, $item->id, $qty, $warehouseId);
            $store->issue($this->tenantId, $item->id, $qty, $warehouseId, 'Seeded issue');
        }
    }

    private function warehouseWithStock(int $productId, float $qty): int
    {
        $id = DB::table('product_warehouse_stocks')
            ->where('tenant_id', $this->tenantId)
            ->where('product_id', $productId)
            ->whereRaw('(quantity - COALESCE(reserved_qty, 0)) >= ?', [$qty])
            ->orderByDesc(DB::raw('quantity - COALESCE(reserved_qty, 0)'))
            ->value('warehouse_id');
        if (!$id) {
            throw new RuntimeException("no warehouse holds {$qty} of product #{$productId}.");
        }

        return (int) $id;
    }

    /** Schedules → Generate (releases a draft / locked order) → Release to the shop floor. */
    private function schedule(ProductionOrder $order, int $startDays): ProductionSchedule
    {
        $order = $order->fresh();
        if ($order->isPreRelease()) {
            app(ProductionOrderService::class)->release($order->id, $this->userId, true);
        }
        $schedule = app(SchedulingService::class)->generateSchedule(
            $order->fresh(),
            now()->addDays($startDays)->setTime(9, 0),
            ProductionSchedule::TYPE_FORWARD
        );
        $schedule->update([
            'status' => ProductionSchedule::STATUS_RELEASED,
            'released_at' => now(),
            'released_by' => $this->userId,
        ]);

        return $schedule->fresh();
    }

    /** The order's operation of that routing sequence (the primary, not an alternative). */
    private function op(ProductionOrder $order, int $sequence): ProductionOrderOperation
    {
        // an alternative has its own routing sequence (OP-020 alt of OP-010), so the sequence picks the primary
        $op = ProductionOrderOperation::where('production_order_id', $order->id)
            ->whereHas('routingOperation', fn ($q) => $q->where('sequence', $sequence))
            ->orderBy('id')
            ->first();
        if (!$op) {
            throw new RuntimeException("order {$order->order_number} has no operation {$sequence}.");
        }

        return $op;
    }

    /**
     * MES: start the operation and log output. On a QC operation the logged units wait in its open inspection record;
     * each [$qty, $passed = true] in $records is one performed record (QD: one quantity, one result).
     *
     * @param array<int, array{0: float|int, 1?: bool}> $records
     */
    private function runInHouse(ProductionOrder $order, int $sequence, array $records, ?float $logQty = null): void
    {
        $op = $this->op($order, $sequence);
        $schedOp = ProductionScheduleOperation::where('production_order_operation_id', $op->id)->latest('id')->first()
            ?? throw new RuntimeException("operation {$op->operation_number} is not scheduled.");
        $mes = app(MesExecutionService::class);

        if ($schedOp->fresh()->status !== ProductionScheduleOperation::STATUS_RUNNING) {
            $mes->startOperation($schedOp->id, null, $this->userId);
        }

        $qty = $logQty ?? (float) array_sum(array_map(fn ($r) => (float) $r[0], $records));
        $mes->completeOperation($schedOp->id, [
            'quantity_produced' => $qty,
            'quantity_rejected' => 0,
            'quantity_scrapped' => 0,
            'log_date' => now()->toDateString(),
            'start_time' => '09:00',
            'end_time' => '13:00',
            'operator_id' => $this->userId,
            'remarks' => 'Seeded production log',
        ], $this->userId);

        foreach ($records as $record) {
            $this->inspect($op->fresh(), (float) $record[0], $record[1] ?? true);
        }
    }

    /**
     * The operation's inspection page: perform its open record for $qty units. Passed = every check inside its limits;
     * failed = the first check of the first plan out of its limits (all others inside).
     */
    private function inspect(ProductionOrderOperation $op, float $qty, bool $passed): void
    {
        $qc = app(QualityInspectionService::class);
        if (!$qc->operationNeedsQc($op)) {
            return;
        }
        $open = $qc->openOperationInspection($op, $this->userId)
            ?? throw new RuntimeException("operation {$op->operation_number} has no open inspection.");

        $values = [];
        $planIds = [];
        $spoilt = false;
        foreach (app(OrderOperationQualityService::class)->checklistFor($op) as $link) {
            $plan = $link->plan;
            if (!$plan) {
                continue;
            }
            $planIds[] = $plan->id;
            foreach ($plan->parameters as $param) {
                $fail = !$passed && !$spoilt;
                $values[$plan->id][$param->id] = $this->reading($param, $fail);
                $spoilt = $spoilt || ($fail && $param->type !== ProductionQualityPlanParameter::TYPE_TEXT);
            }
        }

        $qc->performOperationInspection($open, [
            'quantity' => $qty,
            'parameter_values' => $values,
            'inspected_plans' => $planIds,
            'remarks' => $passed ? 'Seeded inspection — all checks inside limits.' : 'Seeded inspection — check out of limits.',
            'result' => $passed ? 'passed' : 'failed', // used only when the operation has no plan
        ], $this->userId);
    }

    private function reading(ProductionQualityPlanParameter $param, bool $fail): string
    {
        if ($param->type === ProductionQualityPlanParameter::TYPE_NUMERIC) {
            $min = $param->min_value !== null ? (float) $param->min_value : null;
            $max = $param->max_value !== null ? (float) $param->max_value : null;
            if ($fail) {
                return (string) ($max !== null ? $max + max(1.0, abs($max) * 0.1) : ($min !== null ? $min - max(1.0, abs($min) * 0.1) : -1));
            }

            return (string) match (true) {
                $min !== null && $max !== null => round(($min + $max) / 2, 4),
                $min !== null => $min,
                $max !== null => $max,
                default => 1,
            };
        }
        if ($param->type === ProductionQualityPlanParameter::TYPE_PASS_FAIL) {
            return $fail ? 'fail' : 'pass';
        }

        return 'OK';
    }

    /** Subcontracting → Dispatch slip, pre-filled the way the form does (previous output + our BOM rows), dispatched. */
    private function dispatchSlip(ProductionOrderOperation $op, float $units, int $dateDays): DeliveryChallan
    {
        $balance = app(SubcontractMaterialBalanceService::class);
        $items = collect($balance->prefillLines($op, $units))
            ->filter(fn ($line) => (float) $line['quantity'] > 0)
            ->map(fn ($line) => [
                'item_type' => $line['item_type'],
                'product_id' => $line['product_id'],
                'production_batch_id' => $line['production_batch_id'] ?? null,
                'production_wip_id' => $line['production_wip_id'] ?? null,
                'quantity' => $line['quantity'],
                'required_qty' => $line['required_qty'] ?? $line['quantity'],
                'unit_of_measure' => $line['uom'] ?? null,
            ])
            ->values()
            ->all();
        if ($items === []) {
            throw new RuntimeException("nothing to send for {$op->operation_number}.");
        }

        $problems = $balance->dispatchProblems($op, (int) $op->vendor_id, $items, $units);
        if (!empty($problems)) {
            throw new RuntimeException('dispatch refused: ' . implode(' | ', (array) $problems));
        }

        $date = now()->addDays($dateDays);
        $challan = app(DeliveryChallanRepositoryInterface::class)->create($this->tenantId, [
            'production_order_id' => $op->production_order_id,
            'production_order_operation_id' => $op->id,
            'vendor_id' => $op->vendor_id,
            'challan_date' => $date->toDateString(),
            'expected_return_date' => $date->copy()->addHours((int) ceil($op->vendorTotalHours()))->toDateString(),
            'units_sent' => $units,
            'vehicle_number' => 'MH12 AB 4321',
            'transporter_name' => 'Seeded Transport',
            'notes' => 'Seeded dispatch slip',
            'status' => DeliveryChallan::STATUS_DRAFT,
        ], $items);
        $balance->processDispatchActions($challan->fresh('items'), $this->tenantId);

        return $challan->fresh();
    }

    /** Subcontract receipt page: units back from the vendor (with QC they wait in the operation's open record). */
    private function receive(ProductionOrderOperation $op, float $qty, ?DeliveryChallan $challan, bool $final): ProductionSubcontractReceipt
    {
        return app(SubcontractReceiptService::class)->receive(
            $op->fresh(),
            ['received_qty' => $qty, 'rejected_qty' => 0, 'scrapped_qty' => 0, 'final' => $final, 'remarks' => 'Seeded receipt'],
            $this->userId,
            $challan ? ProductionSubcontractReceipt::SOURCE_CHALLAN : ProductionSubcontractReceipt::SOURCE_SHOP_FLOOR,
            $challan?->fresh()
        );
    }

    /** "Create vendor bill" on the receipt → Service Bill, head Job Work, linked to the receipt's GRN (one bill per GRN). */
    private function vendorBill(ProductionOrderOperation $op, float $amount, string $invoicePrefix): void
    {
        $receipts = ProductionSubcontractReceipt::where('production_order_operation_id', $op->id)
            ->whereNotNull('goods_receipt_note_id')
            ->orderBy('id')
            ->get();
        if ($receipts->isEmpty()) {
            throw new RuntimeException("no GRN to bill on {$op->operation_number}.");
        }
        $totalQty = max(0.0001, (float) $receipts->sum('accepted_qty'));

        foreach ($receipts as $i => $receipt) {
            $share = round($amount * (float) $receipt->accepted_qty / $totalQty, 2);
            $request = Request::create('/purchase/bills/service', 'POST', [
                'vendor_id' => $op->vendor_id,
                'goods_receipt_note_id' => $receipt->goods_receipt_note_id,
                'service_head' => 'Job Work',
                'amount' => max(0.01, $share),
                'tax_rate' => 18,
                'gst_type' => 'cgst_sgst',
                'bill_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'vendor_invoice_number' => $invoicePrefix . '-' . ($i + 1),
                'notes' => "Order {$op->order?->order_number} · Op {$op->operation_number} · receipt {$receipt->receipt_number}",
            ]);
            $request->setUserResolver(fn () => Auth::user());
            app()->instance('request', $request);
            app(VendorBillController::class)->storeService($request);
        }
    }

    /** WIP final QC when the product has plans, finished-goods receipt into WH-FG-TBL, then complete the order. */
    private function finish(ProductionOrder $order): void
    {
        $order = $order->fresh();
        $qty = (float) $order->quantity_ordered;
        $gate = app(FinalQualityGateService::class);
        if ($gate->requiresFinalQc($order)) {
            $values = [];
            foreach ($gate->plans($order) as $plan) {
                foreach ($plan->parameters as $param) {
                    $values[$plan->id][$param->id] = $this->reading($param, false);
                }
            }
            app(QualityInspectionService::class)->recordFinalInspection($order, [
                'accepted_qty' => $qty,
                'rejected_qty' => 0,
                'parameter_values' => $values,
                'remarks' => 'Seeded final QC',
            ], $this->userId);
        }

        // finals go to the finished-goods store, semis to the WIP store (where the opening stock of semis is)
        $sku = (string) $order->product?->sku;
        app(ProductionExecutionService::class)->receiveFinishedGoods(
            $order->id,
            $qty,
            'passed',
            'Seeded finished-goods receipt',
            $this->userId,
            $this->links->warehouseId(str_starts_with($sku, 'test-final-') ? 'WH-FG-TBL' : 'WH-WIP-TBL')
        );

        $order = $order->fresh();
        if (!$order->isCompleted() && !$order->isClosed()) {
            app(ProductionOrderService::class)->complete($order->id, $this->userId);
        }
    }
}
