<?php

namespace Tests\Feature;

use App\Domains\CRM\Models\Lead;
use App\Domains\CRM\Services\LeadImportMapperService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The smart lead importer streams the spreadsheet and inserts in batches, so a
 * large file neither exhausts memory (analyze step) nor times out (import step).
 */
class CrmLeadLargeImportTest extends TestCase
{
    use RefreshDatabase;

    private function makeXlsx(int $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'leads') . '.xlsx';
        $sheet = (new \PhpOffice\PhpSpreadsheet\Spreadsheet())->getActiveSheet();
        $sheet->fromArray(['Lead Type', 'Company Name', 'Contact Person', 'Contact Email', 'Contact Phone', 'Expected Sale Date', 'Priority'], null, 'A1');
        for ($i = 1; $i <= $rows; $i++) {
            $sheet->fromArray(['b2b', "Co {$i}", "Person {$i}", "p{$i}@x.test", '98' . str_pad((string) $i, 8, '0', STR_PAD_LEFT), '2026-12-31', 'High'], null, 'A' . ($i + 1));
        }
        // Row repeating row 1's email: must be treated as a duplicate, not inserted.
        $sheet->fromArray(['b2b', 'Co dup', 'Dup', 'p1@x.test', '9000000000', '', 'Low'], null, 'A' . ($rows + 2));
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($sheet->getParent()))->save($path);

        return $path;
    }

    public function test_large_file_is_analyzed_and_imported_in_batches(): void
    {
        $tenant = Tenant::create(['name' => 'T', 'slug' => 't', 'status' => 'active', 'plan' => 'enterprise']);
        $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Owner', 'email' => 'o@t.test', 'password' => bcrypt('x')]);
        $this->actingAs($user);
        app(\App\Core\Tenant\TenantContext::class)->set($tenant);

        $path = $this->makeXlsx(1200);
        $service = app(LeadImportMapperService::class);

        $parsed = $service->parseFile(new UploadedFile($path, 'leads.xlsx', null, null, true), $tenant->id);
        $this->assertSame(1201, $parsed['total_rows']);
        $this->assertCount(5, $parsed['sample_rows']);
        $this->assertSame('Person 1', $parsed['sample_rows'][0]['Contact Person']);

        $result = $service->processImport(
            fileToken: $parsed['file_token'],
            mapping: $parsed['suggested_mapping'],
            options: ['default_owner_id' => $user->id],
            tenantId: $tenant->id,
            userId: $user->id,
        );

        $this->assertTrue($result['success']);
        $this->assertSame(1200, $result['imported_count']);
        $this->assertSame(1, $result['skipped_count']);

        $leads = Lead::withoutGlobalScopes()->where('tenant_id', $tenant->id);
        $this->assertSame(1200, (clone $leads)->count());
        $this->assertSame(1200, (clone $leads)->distinct()->count('lead_number'));
        $first = (clone $leads)->where('email', 'p1@x.test')->first();
        $this->assertSame('Person 1', $first->contact_person);
        $this->assertSame('2026-12-31', $first->expected_sale_date->toDateString());
        $this->assertSame($user->id, (int) $first->lead_owner_id);

        // The next lead created normally continues the same numbering.
        $next = Lead::create(['tenant_id' => $tenant->id, 'company_name' => 'After import', 'call_date' => now()]);
        $this->assertSame(1201, (int) substr($next->lead_number, -4));
    }
}
