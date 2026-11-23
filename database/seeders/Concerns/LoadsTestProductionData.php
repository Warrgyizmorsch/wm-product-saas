<?php

namespace Database\Seeders\Concerns;

use App\Models\Tenant;
use Database\Seeders\Support\TestProductionLinks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * What the three TestProduction*Seeder classes share: finding the tenant and writing their rows (with every natural
 * key translated by TestProductionLinks).
 */
trait LoadsTestProductionData
{
    /** The tenant the data goes to: the one passed in, else the fallback tenant of this installation. */
    protected function resolveTenantId(?int $tenantId): int
    {
        if ($tenantId) {
            return $tenantId;
        }

        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (! $tenant) {
            throw new RuntimeException('Test production data: there is no tenant to seed into.');
        }

        return (int) $tenant->id;
    }

    /**
     * Inserts rows that carry their own id (matched on id + tenant, so another tenant's row is never overwritten:
     * a clash fails loudly). Columns the table does not have are dropped.
     */
    protected function seedTable(string $table, array $rows, int $tenantId, TestProductionLinks $links): void
    {
        if (empty($rows)) {
            return;
        }

        $columns = array_flip(Schema::getColumnListing($table));
        $hasTenantId = isset($columns['tenant_id']);

        foreach ($rows as $row) {
            if ($hasTenantId) {
                $row['tenant_id'] = $tenantId;
            }

            $row = array_intersect_key($links->translate($table, $row), $columns);

            if (isset($row['id'])) {
                DB::table($table)->updateOrInsert($hasTenantId ? ['id' => $row['id'], 'tenant_id' => $tenantId] : ['id' => $row['id']], $row);
            } else {
                DB::table($table)->insert($row);
            }
        }
    }

    /**
     * Quantity the orders take out of each (product, warehouse), keyed "product:warehouse".
     *
     * @param  array<int, array<string, mixed>>  $issues  rows of production_order_issues (with a 'warehouse' code)
     * @return array<string, float>
     */
    protected function issuedByProductWarehouse(array $issues, TestProductionLinks $links): array
    {
        $issued = [];
        foreach ($issues as $issue) {
            $key = $issue['product_id'] . ':' . $links->warehouseId($issue['warehouse']);
            $issued[$key] = ($issued[$key] ?? 0.0) + (float) $issue['quantity_issued'];
        }

        return $issued;
    }
}
