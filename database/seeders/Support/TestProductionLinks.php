<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Turns the natural keys written in the test production data into the ids of the database being seeded.
 *
 * The data never carries an id for a row it does not own. It says 'uom' => 'Pcs', 'warehouse' => 'WH-RM-TBL',
 * 'shift' => 'DAY', 'employee' => 'WRG-002', 'branch' => 'HQ', 'company' => 'default' (the tenant's first company),
 * accounts by chart code — and this class finds those rows again by code or name for the tenant. After the
 * clean-and-reseed process ids of units, warehouses and shifts are not predictable (another tenant's rows push them
 * up, HRMS decides its own order), codes are.
 *
 * A key that cannot be resolved stops the seeding with a message that names it: a half-linked dataset is worse
 * than no dataset.
 */
class TestProductionLinks
{
    /** pseudo column in the data => [real column, table, lookup column] */
    private const KEYS = [
        'branch' => ['branch_id', 'branches', 'code'],
        'business_unit' => ['business_unit_id', 'business_units', 'code'],
        'department' => ['department_id', 'departments', 'name'],
        'pay_group' => ['pay_group_id', 'pay_groups', 'name'],
        'leave_plan' => ['leave_plan_id', 'leave_plans', 'name'],
        'salary_structure' => ['salary_structure_id', 'salary_structures', 'name'],
        'uom' => ['uom_id', 'uoms', 'code'],
        'base_uom' => ['base_uom_id', 'uoms', 'code'],
        'warehouse' => ['warehouse_id', 'warehouses', 'code'],
        'shift' => ['shift_id', 'production_shifts', 'code'],
        'employee' => ['employee_id', 'employees', 'employee_id'],
    ];

    /** products columns that hold a chart-of-accounts id; the data gives the account code */
    private const ACCOUNT_COLUMNS = ['inventory_account', 'sales_account', 'purchase_account'];

    /** job title keyword (first match wins) => designation; titles with no keyword become "Production Technician" */
    private const DESIGNATIONS = [
        ['keywords' => ['Supervisor', 'Lead'], 'name' => 'Production Supervisor', 'level' => 'L3'],
        ['keywords' => ['Inspector', 'Auditor'], 'name' => 'Quality Inspector', 'level' => 'L2'],
        ['keywords' => ['Assembler', 'Fabricator', 'Artisan'], 'name' => 'Assembler / Fabricator', 'level' => 'L2'],
        ['keywords' => ['Operator'], 'name' => 'Machine Operator', 'level' => 'L2'],
        ['keywords' => ['Maintenance'], 'name' => 'Maintenance Technician', 'level' => 'L2'],
    ];
    private const DEFAULT_DESIGNATION = ['name' => 'Production Technician', 'level' => 'L2'];

    /** the department the test employees (and their designations) belong to */
    public const EMPLOYEE_DEPARTMENT = 'Production & Operations';

    /** @var array<string, int> */
    private array $cache = [];

    public function __construct(private readonly int $tenantId)
    {
    }

    /** Rewrites the natural keys of one row into real columns. Everything else is left alone. */
    public function translate(string $table, array $row): array
    {
        if (array_key_exists('company', $row)) {
            unset($row['company']);
            $row['company_id'] = $this->companyId();
        }

        foreach (self::KEYS as $key => [$column, $targetTable, $lookupColumn]) {
            if (array_key_exists($key, $row)) {
                $row[$column] = $this->find($targetTable, $lookupColumn, (string) $row[$key]);
                unset($row[$key]);
            }
        }

        if (isset($row['assigned_shifts']) && is_array($row['assigned_shifts'])) {
            $row['assigned_shifts'] = json_encode(array_map(fn ($code) => $this->find('production_shifts', 'code', (string) $code), $row['assigned_shifts']));
        }

        if ($table === 'products') {
            foreach (self::ACCOUNT_COLUMNS as $column) {
                if (isset($row[$column]) && $row[$column] !== '') {
                    $row[$column] = $this->find('chart_of_accounts', 'code', (string) $row[$column]);
                }
            }
        }

        if ($table === 'employees') {
            $row['designation_id'] = $this->designationId((string) ($row['job_title'] ?? ''));
        }

        return $row;
    }

    /** The tenant's first company: what 'company' => 'default' stands for. */
    public function companyId(): int
    {
        return $this->cache['company'] ??= (int) (DB::table('companies')->where('tenant_id', $this->tenantId)->orderBy('id')->value('id')
            ?? $this->missing('company', 'the first one of the tenant'));
    }

    public function branchId(string $code): int
    {
        return $this->find('branches', 'code', $code);
    }

    public function warehouseId(string $code): int
    {
        return $this->find('warehouses', 'code', $code);
    }

    /** Creates the production designations the test employees need (added once, found again on every later run). */
    public function ensureDesignations(array $employees): void
    {
        $departmentId = $this->find('departments', 'name', self::EMPLOYEE_DEPARTMENT);

        foreach ($employees as $employee) {
            $definition = $this->designationFor((string) ($employee['job_title'] ?? ''));

            if (! DB::table('designations')->where('department_id', $departmentId)->where('name', $definition['name'])->exists()) {
                $row = ['tenant_id' => $this->tenantId, 'department_id' => $departmentId, 'name' => $definition['name'], 'level' => $definition['level'], 'status' => true, 'created_at' => now(), 'updated_at' => now()];
                DB::table('designations')->insert(array_intersect_key($row, array_flip(Schema::getColumnListing('designations'))));
            }
        }
    }

    /** @return array{name:string, level:string} */
    public function designationFor(string $jobTitle): array
    {
        foreach (self::DESIGNATIONS as $definition) {
            foreach ($definition['keywords'] as $keyword) {
                if (stripos($jobTitle, $keyword) !== false) {
                    return ['name' => $definition['name'], 'level' => $definition['level']];
                }
            }
        }

        return self::DEFAULT_DESIGNATION;
    }

    private function designationId(string $jobTitle): int
    {
        $departmentId = $this->find('departments', 'name', self::EMPLOYEE_DEPARTMENT);
        $name = $this->designationFor($jobTitle)['name'];
        $id = DB::table('designations')->where('department_id', $departmentId)->where('name', $name)->value('id');

        return $id !== null ? (int) $id : $this->missing('designation', $name);
    }

    /** The id of the tenant's row of $table whose $column is $value (the oldest one if there are several). */
    private function find(string $table, string $column, string $value): int
    {
        return $this->cache["{$table}.{$column}.{$value}"] ??= (int) (DB::table($table)->where('tenant_id', $this->tenantId)->where($column, $value)->orderBy('id')->value('id')
            ?? $this->missing($table, "{$column} '{$value}'"));
    }

    private function missing(string $what, string $key): never
    {
        throw new RuntimeException("Test production data: no {$what} found for {$key} (tenant {$this->tenantId}). Run the base seeders (db:seed) first.");
    }
}
