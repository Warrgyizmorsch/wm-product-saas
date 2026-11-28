<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * CleanBeforeHrmsSeeder — the "cleaning" half of the test-data reset.
 *
 * Runs after InventoryMasterDemoSeeder and BEFORE HrmsDemoSeeder (see DatabaseSeeder). It empties every table that
 * HrmsDemoSeeder, the test production data and the client demos fill, so that
 *
 *   - HrmsDemoSeeder starts from empty tables: its "protected employee" rule (which switches TRUNCATE to DELETE and makes
 *     ids drift on every run) finds nothing to protect, and every id it creates restarts at 1;
 *   - AddTestMastersSeeder and the test data that follows only ever ADD rows.
 *
 * What is wiped (companies, business units and branches are never touched — they come from InventoryMasterDemoSeeder):
 *
 *   1. HR_TABLES         the 104 tables HrmsDemoSeeder wipes itself (same list, same global TRUNCATE as HrmsDemoSeeder)
 *   2. HR_EXTRA_TABLES   tables HrmsDemoSeeder writes to without wiping them
 *   3. production domain every `production_*` table and `routings`
 *   4. INVENTORY_TABLES  products, stock, batches, serial numbers, remnants
 *   5. warehouses        every warehouse of the tenant except those InventoryMasterDemoSeeder creates
 *   6. uoms              every unit of the tenant (AddTestMastersSeeder and the tenant provisioner add them back)
 *   7. stock journals    the accounting journals that stock seeding posted (opening stock, production consumption)
 *   8. subcontract docs  the Purchase side of subcontract receipts (TestProductionOrderSeeder): GRNs with source
 *                        subcontract_receipt, their lines, the vendor bills made from them (lines, bill references) and
 *                        those bills' journals. Other GRNs, bills and journals are never touched.
 *
 * Ids: a table is TRUNCATEd (ids restart at 1) unless it holds rows of another tenant or system rows without a tenant; then only
 * the tenant's rows are deleted and the auto-increment is moved back to max(id)+1, so the next ids are the same on every run.
 *
 * Safety: runs only in the local / testing environment. Everything it deletes is first written to
 * storage/app/backups/seed-wipe-<timestamp>/<table>.jsonl (one JSON row per line) unless SEED_WIPE_BACKUP=false
 * (never written in tests). The newest KEEP_BACKUPS folders are kept, older ones are deleted.
 */
class CleanBeforeHrmsSeeder extends Seeder
{
    /**
     * Identical to the `$tablesToTruncate` list in HrmsDemoSeeder::run() — a test keeps the two in step.
     * Wiped with a global TRUNCATE, exactly like HrmsDemoSeeder does it.
     *
     * @var string[]
     */
    public const HR_TABLES = [
        'feedback_360_responses', 'feedback_360_nominations', 'feedback_360_participants', 'feedback_360_questions',
        'feedback_360_competencies', 'feedback_360_cycles', 'goal_check_ins', 'goal_key_results', 'goal_employees',
        'goals', 'goal_categories', 'goal_cycles', 'sop_version_histories', 'sop_assignments', 'sop_sections',
        'sop_documents', 'sop_categories', 'goal_progress_logs', 'employee_goal_items', 'employee_goal_plans',
        'appraisal_cycles', 'kpi_template_items', 'kpi_templates', 'kpi_masters', 'kra_categories',
        'employee_profile_update_requests', 'wfh_requests', 'shift_change_requests', 'overtime_requests',
        'leave_encashments', 'leave_requests', 'leave_balances', 'employee_adhoc_components',
        'employee_employment_histories', 'employee_penalties', 'shift_rosters', 'asset_allocations',
        'asset_requests', 'asset_depreciation_schedules', 'asset_disposals', 'asset_write_offs',
        'asset_revaluations', 'assets', 'asset_items', 'asset_categories', 'generated_documents', 'documents',
        'document_templates', 'document_masters', 'document_categories', 'attendance_corrections',
        'attendance_location_logs', 'attendance_breaks', 'biometric_punch_logs', 'biometric_devices', 'attendances',
        'attendance_penalties', 'attendance_rules', 'holiday_calendars', 'cash_advances', 'expense_claims',
        'expense_reports', 'expense_approval_workflows', 'expense_policy_rules', 'expense_policies',
        'expense_categories', 'travel_requests', 'pip_checkins', 'pip_objectives', 'performance_improvement_plans',
        'pip_policy_templates', 'pip_categories', 'broadcast_comments', 'broadcast_receipts', 'broadcasts',
        'interview_scorecards', 'candidate_interviews', 'job_offers', 'candidate_applications', 'candidates',
        'job_requisitions', 'helpdesk_satisfaction_ratings', 'helpdesk_ticket_attachments',
        'helpdesk_ticket_replies', 'helpdesk_tickets', 'helpdesk_kb_articles', 'helpdesk_categories',
        'payroll_retroactive_adjustments', 'payroll_holds', 'salary_revisions', 'payroll_runs',
        'employee_exit_documents', 'employee_fnf_settlements', 'employee_exit_clearances', 'employee_exits',
        'exit_clearance_templates', 'employee_probation_evaluations', 'employees', 'salary_structure_items',
        'salary_structures', 'salary_components', 'pay_groups', 'leave_types', 'leave_plans',
    ];

    /**
     * Tables HrmsDemoSeeder writes to but does not wipe — more to wipe here if wanted. Audit of a fresh run (HRMS touched 101
     * tables; 7 are outside its own list):
     *
     *   production_shifts          wiped below with the production domain
     *   branches, business_units   organisation masters from InventoryMasterDemoSeeder — never wiped
     *   users, user_roles          logins; 168 foreign keys from every module point at users — never wiped
     *   departments                users.department_id and user_roles.department_id point at it — kept
     *   designations               kept: HRMS matches both departments and designations by code (firstOrCreate), so their ids
     *                              do not drift; add 'designations' here to also drop designations someone added by hand
     *
     * @var string[]
     */
    public const HR_EXTRA_TABLES = [];

    /** @var string[] */
    public const INVENTORY_TABLES = [
        'products', 'product_images', 'product_warehouse_stocks', 'stock_transactions', 'stock_reservations',
        'stock_adjustments', 'stock_adjustment_items', 'stock_transfers', 'stock_transfer_items', 'batches',
        'serial_numbers', 'inventory_remnants', 'inventory_remnant_consumptions',
    ];

    /** Warehouses created by InventoryMasterDemoSeeder — every other warehouse of the tenant is removed. */
    public const KEEP_WAREHOUSES = ['WH-MAIN', 'WH-SEC'];

    /** Backup folders kept (newest first); older ones are deleted after each run so repeated resets do not fill the disk. */
    public const KEEP_BACKUPS = 5;

    private ?string $backupDir = null;

    /** @var array<string, array{mode:string, rows:int}> */
    private array $report = [];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->error('CleanBeforeHrmsSeeder wipes data and only runs in the local / testing environment (APP_ENV=' . app()->environment() . ').');

            return;
        }

        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (! $tenant) {
            return;
        }
        $tenantId = (int) $tenant->id;

        $this->prepareBackup();

        Schema::disableForeignKeyConstraints();
        try {
            foreach (self::HR_TABLES as $table) {
                $this->wipe($table, $tenantId, global: true);
            }
            foreach (self::HR_EXTRA_TABLES as $table) {
                $this->wipe($table, $tenantId);
            }
            foreach ($this->productionTables() as $table) {
                $this->wipe($table, $tenantId);
            }
            $this->wipeProductionSequences($tenantId);
            foreach (self::INVENTORY_TABLES as $table) {
                $this->wipe($table, $tenantId);
            }
            $this->wipeWarehouses($tenantId);
            $this->wipe('uoms', $tenantId);
            $this->wipeStockJournals($tenantId);
            $this->wipeSubcontractPurchaseDocuments($tenantId);
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->finishBackup();

        $rows = array_sum(array_column($this->report, 'rows'));
        $scoped = array_keys(array_filter($this->report, fn ($r) => $r['mode'] === 'tenant rows'));
        $this->command?->info('CleanBeforeHrmsSeeder: ' . count($this->report) . " tables wiped, {$rows} rows removed"
            . ($scoped ? ' (tenant rows only, ids kept above other tenants: ' . implode(', ', $scoped) . ')' : '')
            . ($this->backupDir ? ". Backup: {$this->backupDir}" : '.'));
    }

    /**
     * Production tables without the `production_` prefix. The dispatch slips (subcontract delivery challans) belong to
     * production orders: left behind, they would attach to the new orders once the order ids restart at 1.
     */
    public const PRODUCTION_UNPREFIXED_TABLES = ['routings', 'delivery_challans', 'delivery_challan_items'];

    /** Every `production_*` table plus the unprefixed ones above, found at run time so a new production table is cleaned too. */
    private function productionTables(): array
    {
        return collect(Schema::getTables())
            ->pluck('name')
            ->filter(fn ($name) => str_starts_with($name, 'production_') || in_array($name, self::PRODUCTION_UNPREFIXED_TABLES, true))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Empty one table. TRUNCATE (ids restart at 1) unless the table also holds another tenant's rows or rows without a tenant;
     * then only the tenant's rows go and the auto-increment moves back to max(id)+1.
     */
    private function wipe(string $table, int $tenantId, bool $global = false): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        if ($global || ! $this->holdsOtherRows($table, $tenantId)) {
            $rows = DB::table($table)->count();
            $this->backup($table, fn ($q) => $q);
            DB::table($table)->truncate();
            $this->report[$table] = ['mode' => 'truncate', 'rows' => $rows];

            return;
        }

        $this->deleteTenantRows($table, $tenantId, fn ($q) => $q);
    }

    /** Delete the tenant's rows (optionally narrowed by $scope) from a table that must keep other rows. */
    private function deleteTenantRows(string $table, int $tenantId, \Closure $scope): void
    {
        $query = fn () => $scope(DB::table($table)->where('tenant_id', $tenantId));

        $rows = $query()->count();
        $this->backup($table, fn ($q) => $scope($q->where('tenant_id', $tenantId)));
        $query()->delete();
        $this->resetAutoIncrement($table);
        $this->report[$table] = ['mode' => 'tenant rows', 'rows' => $rows];
    }

    private function holdsOtherRows(string $table, int $tenantId): bool
    {
        if (! Schema::hasColumn($table, 'tenant_id')) {
            return false;
        }

        return DB::table($table)
            ->where(fn ($q) => $q->where('tenant_id', '!=', $tenantId)->orWhereNull('tenant_id'))
            ->exists();
    }

    /** Number counters (tenant_sequences keys) of production rows wiped above, so the reseeded numbers restart at 1. */
    public const PRODUCTION_SEQUENCE_KEYS = ['quality_plan'];

    /** SR-08: QP-0001… is issued from tenant_sequences key "quality_plan"; with the plans gone, the counter goes too. */
    private function wipeProductionSequences(int $tenantId): void
    {
        if (! Schema::hasTable('tenant_sequences')) {
            return;
        }

        $this->deleteTenantRows('tenant_sequences', $tenantId, fn ($q) => $q->whereIn('key', self::PRODUCTION_SEQUENCE_KEYS));
    }

    /**
     * The Purchase / Accounting rows of subcontract receipts (SF-4 / SF-5): each booked receipt writes a stock-free GRN
     * (source_type subcontract_receipt) and its vendor bill is a Service Bill linked to that GRN. The receipts themselves
     * are production rows (wiped above); left behind, these would point at deleted receipts and double the 5034 balance on
     * the next seed. Only rows reachable from a subcontract-receipt GRN are removed.
     */
    private function wipeSubcontractPurchaseDocuments(int $tenantId): void
    {
        if (! Schema::hasTable('goods_receipt_notes') || ! Schema::hasColumn('goods_receipt_notes', 'source_type')) {
            return;
        }

        $grnIds = DB::table('goods_receipt_notes')->where('tenant_id', $tenantId)->where('source_type', 'subcontract_receipt')->pluck('id')->all();
        if ($grnIds === []) {
            return;
        }
        $billIds = Schema::hasTable('vendor_bills')
            ? DB::table('vendor_bills')->where('tenant_id', $tenantId)->whereIn('goods_receipt_note_id', $grnIds)->pluck('id')->all()
            : [];

        $steps = [];
        if ($billIds !== []) {
            $journalIds = Schema::hasTable('journals')
                ? DB::table('journals')->where('tenant_id', $tenantId)->where('reference_type', 'vendor_bill')->whereIn('reference_id', $billIds)->pluck('id')->all()
                : [];
            if ($journalIds !== []) {
                $steps['journal_entries'] = fn ($q) => $q->whereIn('journal_id', $journalIds);
                $steps['journals'] = fn ($q) => $q->whereIn('id', $journalIds);
            }
            $steps['bill_references'] = fn ($q) => $q->where('tenant_id', $tenantId)->whereIn('source_id', $billIds)
                ->whereIn('source_type', ['vendor_bill', \App\Domains\Purchase\Models\VendorBill::class]);
            $steps['vendor_bill_items'] = fn ($q) => $q->whereIn('vendor_bill_id', $billIds);
            $steps['vendor_bills'] = fn ($q) => $q->whereIn('id', $billIds);
        }
        $steps['goods_receipt_note_items'] = fn ($q) => $q->whereIn('goods_receipt_note_id', $grnIds);
        $steps['goods_receipt_notes'] = fn ($q) => $q->whereIn('id', $grnIds);

        $rows = 0;
        foreach ($steps as $table => $scope) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $this->backup($table, $scope);
            $rows += $scope(DB::table($table))->delete();
        }

        $this->report['subcontract GRNs / bills / journals'] = ['mode' => 'tenant rows', 'rows' => $rows];
    }

    /** Warehouses of the tenant except the ones InventoryMasterDemoSeeder creates. */
    private function wipeWarehouses(int $tenantId): void
    {
        if (! Schema::hasTable('warehouses')) {
            return;
        }

        $this->deleteTenantRows('warehouses', $tenantId, fn ($q) => $q->whereNotIn('code', self::KEEP_WAREHOUSES));
    }

    /**
     * The accounting side of seeded stock: opening-stock journals (source inventory, reference ProductOpeningStock) and
     * production consumption journals (source production, reference stock_transaction). The stock they describe is wiped above;
     * leaving them would keep an inventory balance with no stock, and re-seeding stock would post them twice.
     */
    private function wipeStockJournals(int $tenantId): void
    {
        if (! Schema::hasTable('journals') || ! Schema::hasTable('journal_entries')) {
            return;
        }

        $ids = DB::table('journals')
            ->where('tenant_id', $tenantId)
            ->where(function ($q) {
                $q->where('reference_type', 'ProductOpeningStock')
                    ->orWhere(fn ($w) => $w->where('source', 'production')->where('reference_type', 'stock_transaction'));
            })
            ->pluck('id')
            ->all();

        if ($ids === []) {
            return;
        }

        foreach (array_chunk($ids, 500) as $chunk) {
            $this->backup('journal_entries', fn ($q) => $q->whereIn('journal_id', $chunk));
            $this->backup('journals', fn ($q) => $q->whereIn('id', $chunk));
            DB::table('journal_entries')->whereIn('journal_id', $chunk)->delete();
            DB::table('journals')->whereIn('id', $chunk)->delete();
        }

        $this->report['journals (stock postings)'] = ['mode' => 'tenant rows', 'rows' => count($ids)];
    }

    /** MySQL: AUTO_INCREMENT = 1 is raised by the server to max(id)+1. SQLite: the sequence is set to max(id). */
    private function resetAutoIncrement(string $table): void
    {
        $driver = DB::connection()->getDriverName();

        try {
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement("ALTER TABLE `{$table}` AUTO_INCREMENT = 1");
            } elseif ($driver === 'sqlite') {
                DB::update('update sqlite_sequence set seq = (select coalesce(max(id), 0) from "' . $table . '") where name = ?', [$table]);
            }
        } catch (\Throwable) {
            // a table without an auto-increment id has nothing to reset
        }
    }

    private function prepareBackup(): void
    {
        if (app()->environment('testing') || ! filter_var(env('SEED_WIPE_BACKUP', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $dir = storage_path('app/backups/seed-wipe-' . date('Ymd-His'));
        if (@mkdir($dir, 0775, true) || is_dir($dir)) {
            $this->backupDir = $dir;
        }
    }

    /** Drop this run's folder when nothing was backed up, and keep only the newest KEEP_BACKUPS folders. */
    private function finishBackup(): void
    {
        if ($this->backupDir === null) {
            return;
        }

        if ((glob($this->backupDir . '/*') ?: []) === []) {
            @rmdir($this->backupDir);
            $this->backupDir = null;
        }

        $folders = glob(storage_path('app/backups/seed-wipe-*'), GLOB_ONLYDIR) ?: [];
        rsort($folders); // the timestamp in the name sorts newest first
        foreach (array_slice($folders, self::KEEP_BACKUPS) as $old) {
            File::deleteDirectory($old);
        }
    }

    /** Write the rows a wipe is about to delete (one JSON object per line); empty tables write nothing. */
    private function backup(string $table, \Closure $scope): void
    {
        if ($this->backupDir === null) {
            return;
        }

        $query = $scope(DB::table($table));
        if (! $query->exists()) {
            return;
        }

        $handle = fopen($this->backupDir . '/' . $table . '.jsonl', 'ab');
        if (! $handle) {
            return;
        }
        foreach ($query->cursor() as $row) {
            fwrite($handle, json_encode($row, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n");
        }
        fclose($handle);
    }
}
