<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\Gstr2bImport;
use App\Domains\Accounting\Models\Gstr2bLine;
use App\Domains\Accounting\Services\Gst\Gstr2bReconciliationService;
use App\Domains\HRMS\Models\Company;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\VendorBill;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * GSTR-2B reconciliation: portal JSON in, each supplier document matched
 * against the vendor bills in the books.
 */
class Gstr2bReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const STEEL = '27AABCS1234F1Z5';
    private const PAPER = '29AAACP5678K1Z2';
    private const STRANGER = '07AAACX9999L1Z0';

    private Tenant $tenant;
    private User $accountant;
    private Vendor $steel;
    private Vendor $paper;
    private int $billSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        app(TenantContext::class)->set($this->tenant);

        $this->accountant = $this->makeUser('Asha Accounts', 'asha@acme.test', 'accountant');
        $this->steel = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Steel Co', 'gstin' => self::STEEL, 'status' => 'active']);
        $this->paper = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Paper Mart', 'gstin' => strtolower(self::PAPER), 'status' => 'active']);
    }

    private function makeUser(string $name, string $email, string $role): User
    {
        $user = User::create(['tenant_id' => $this->tenant->id, 'name' => $name, 'email' => $email, 'password' => bcrypt('password')]);
        UserRole::create([
            'user_id' => $user->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', $role)->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

        return $user;
    }

    /** A posted bill; intra-state (CGST+SGST) unless $igst. */
    private function bill(Vendor $vendor, string $invoiceNo, string $date, float $taxable, float $rate = 18, bool $igst = false, array $extra = []): VendorBill
    {
        $tax = round($taxable * $rate / 100, 2);

        return VendorBill::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'bill_number' => 'BILL-' . str_pad((string) ++$this->billSeq, 4, '0', STR_PAD_LEFT),
            'vendor_invoice_number' => $invoiceNo,
            'vendor_id' => $vendor->id,
            'bill_date' => $date,
            'status' => 'Unpaid',
            'subtotal' => $taxable,
            'tax_amount' => $tax,
            'igst_amount' => $igst ? $tax : 0,
            'cgst_amount' => $igst ? 0 : $tax / 2,
            'sgst_amount' => $igst ? 0 : $tax / 2,
            'grand_total' => $taxable + $tax,
            'due_amount' => $taxable + $tax,
        ], $extra));
    }

    private function inv(string $number, string $date, float $taxable, float $rate = 18, bool $igst = false, array $extra = []): array
    {
        $tax = round($taxable * $rate / 100, 2);

        return array_merge([
            'inum' => $number, 'typ' => 'R', 'dt' => $date, 'val' => $taxable + $tax, 'pos' => '27', 'rev' => 'N',
            'itcavl' => 'Y', 'rsn' => '',
            'items' => [[
                'num' => 1, 'rt' => $rate, 'txval' => $taxable,
                'igst' => $igst ? $tax : 0, 'cgst' => $igst ? 0 : $tax / 2, 'sgst' => $igst ? 0 : $tax / 2, 'cess' => 0,
            ]],
        ], $extra);
    }

    /** A portal-shaped GSTR-2B for September 2026. */
    private function portalJson(array $b2b, array $cdnr = [], string $gstin = '27AAACA1111A1Z5', string $period = '092026'): string
    {
        return json_encode(['chksum' => 'x', 'data' => [
            'gstin' => $gstin, 'rtnprd' => $period, 'version' => '1.0', 'gendt' => '14-10-2026',
            'docdata' => array_filter(['b2b' => $b2b, 'cdnr' => $cdnr]),
        ]]);
    }

    private function supplier(string $ctin, string $name, array $docs, string $key = 'inv'): array
    {
        return ['ctin' => $ctin, 'trdnm' => $name, 'supfildt' => '11-10-2026', 'supprd' => '092026', $key => $docs];
    }

    private function service(): Gstr2bReconciliationService
    {
        return app(Gstr2bReconciliationService::class);
    }

    private function line(Gstr2bImport $import, string $number): Gstr2bLine
    {
        return $import->lines()->where('document_number', $number)->firstOrFail();
    }

    public function test_parse_reads_portal_json_with_or_without_data_wrapper(): void
    {
        $json = $this->portalJson([$this->supplier(self::STEEL, 'Steel Co', [
            $this->inv('S/001', '05-09-2026', 1000),
            $this->inv('S/002', '06-09-2026', 500, 12, true, ['items' => [
                ['rt' => 12, 'txval' => 300, 'igst' => 36, 'cgst' => 0, 'sgst' => 0, 'cess' => 0],
                ['rt' => 12, 'txval' => 200, 'igst' => 24, 'cgst' => 0, 'sgst' => 0, 'cess' => 0],
            ]]),
        ])]);

        foreach ([$json, json_encode(json_decode($json, true)['data'])] as $variant) {
            $parsed = $this->service()->parse($variant);

            $this->assertSame('092026', $parsed['period']);
            $this->assertSame('2026-10-14', $parsed['generated_on']);
            $this->assertCount(2, $parsed['lines']);
            $this->assertSame('S001', $parsed['lines'][0]['normalized_number']);
            $this->assertSame('2026-09-05', $parsed['lines'][0]['document_date']);
            $this->assertEquals(90.0, $parsed['lines'][0]['cgst']);
            $this->assertEquals(500.0, $parsed['lines'][1]['taxable_value']);
            $this->assertEquals(60.0, $parsed['lines'][1]['igst']);
        }
    }

    public function test_parse_rejects_files_that_are_not_gstr2b(): void
    {
        foreach (['not json', json_encode(['data' => ['gstin' => 'X']]), json_encode(['rtnprd' => '132026', 'docdata' => []])] as $bad) {
            try {
                $this->service()->parse($bad);
                $this->fail('Expected rejection');
            } catch (InvalidArgumentException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_lines_are_classified_matched_mismatch_missing_and_note(): void
    {
        $matched = $this->bill($this->steel, 'S-1', '2026-09-05', 1000);
        $formatted = $this->bill($this->steel, 'INV-42', '2026-09-08', 2000);
        $short = $this->bill($this->steel, 'S-3', '2026-09-10', 900);           // 2B says 1000
        $renumbered = $this->bill($this->paper, 'PM/77', '2026-09-12', 400, 18, true); // 2B has "PM-0077-A"
        $this->bill($this->steel, 'S-9', '2026-09-20', 700, 18, false, ['status' => 'Cancelled']);

        $import = $this->service()->import($this->portalJson([
            $this->supplier(self::STEEL, 'Steel Co', [
                $this->inv('S/1', '05-09-2026', 1000),
                $this->inv('INV/0042', '08-09-2026', 2000),
                $this->inv('S-3', '10-09-2026', 1000),
                $this->inv('S-9', '20-09-2026', 700),       // only a cancelled bill in books
            ]),
            $this->supplier(self::PAPER, 'Paper Mart', [$this->inv('PM-0077-A', '13-09-2026', 400, 18, true)]),
            $this->supplier(self::STRANGER, 'Unknown Traders', [$this->inv('U-1', '01-09-2026', 100, 18, false, ['itcavl' => 'N', 'rsn' => 'P'])]),
        ], [
            $this->supplier(self::STEEL, 'Steel Co', [['ntnum' => 'CN-1', 'typ' => 'C', 'dt' => '25-09-2026', 'val' => 118, 'items' => [['txval' => 100, 'cgst' => 9, 'sgst' => 9]]]], 'nt'),
        ]), 'gstr2b.json', $this->accountant->id);

        $this->assertSame(7, $import->line_count);

        $l = $this->line($import, 'S/1');
        $this->assertSame(Gstr2bLine::MATCHED, $l->match_status);
        $this->assertSame($matched->id, $l->vendor_bill_id);
        $this->assertNull($l->differences);

        $l = $this->line($import, 'INV/0042');
        $this->assertSame(Gstr2bLine::MATCHED, $l->match_status, 'separators and leading zeros are ignored');
        $this->assertSame($formatted->id, $l->vendor_bill_id);

        $l = $this->line($import, 'S-3');
        $this->assertSame(Gstr2bLine::MISMATCH, $l->match_status);
        $this->assertSame($short->id, $l->vendor_bill_id);
        $this->assertStringContainsString('Taxable value', implode(' ', $l->differences));

        $l = $this->line($import, 'PM-0077-A');
        $this->assertSame(Gstr2bLine::MISMATCH, $l->match_status, 'same amount, date within 3 days = probable match');
        $this->assertSame($renumbered->id, $l->vendor_bill_id);
        $this->assertStringContainsString('Invoice number differs', $l->differences[0]);
        $this->assertStringContainsString('Invoice date', implode(' ', $l->differences));

        $this->assertSame(Gstr2bLine::MISSING_IN_BOOKS, $this->line($import, 'S-9')->match_status, 'cancelled bills never match');

        $l = $this->line($import, 'U-1');
        $this->assertSame(Gstr2bLine::MISSING_IN_BOOKS, $l->match_status);
        $this->assertStringContainsString('No vendor in your books has GSTIN', $l->differences[0]);
        $this->assertStringContainsString('ITC not available', implode(' ', $l->differences));

        $note = $this->line($import, 'CN-1');
        $this->assertSame(Gstr2bLine::NOTE, $note->match_status);
        $this->assertSame('credit_note', $note->document_type);

        $s = $import->summary;
        $this->assertSame(2, $s['matched']['count']);
        $this->assertSame(2, $s['mismatch']['count']);
        $this->assertSame(2, $s['missing_in_books']['count']);
        $this->assertSame(1, $s['note']['count']);
        $this->assertEquals(18.0, $s['itc_not_available']);
    }

    public function test_bills_not_in_2b_are_listed_as_vendor_not_filed(): void
    {
        $this->bill($this->steel, 'S-1', '2026-09-05', 1000);
        $unfiled = $this->bill($this->paper, 'PM-5', '2026-09-15', 300);
        $this->bill($this->paper, 'PM-6', '2026-08-31', 300);                        // previous month
        $this->bill($this->paper, 'PM-7', '2026-09-16', 300, 0);                     // no GST
        $this->bill($this->paper, 'PM-8', '2026-09-17', 300, 18, false, ['status' => 'Cancelled']);
        $noGstin = Vendor::create(['tenant_id' => $this->tenant->id, 'name' => 'Local Shop', 'status' => 'active']);
        $this->bill($noGstin, 'L-1', '2026-09-18', 200);

        $import = $this->service()->import($this->portalJson([
            $this->supplier(self::STEEL, 'Steel Co', [$this->inv('S-1', '05-09-2026', 1000)]),
        ]), null, null);

        $booksOnly = $this->service()->booksOnly($import);
        $this->assertSame([$unfiled->id], $booksOnly->pluck('id')->all());
        $this->assertSame(1, $import->summary['books_only']['count']);
        $this->assertEquals(54.0, $import->summary['books_only']['tax']);
        $this->assertSame(1, $this->service()->billsWithoutVendorGstin($import));
    }

    public function test_each_bill_matches_only_one_line(): void
    {
        $bill = $this->bill($this->steel, 'S-1', '2026-09-05', 1000);

        $import = $this->service()->import($this->portalJson([$this->supplier(self::STEEL, 'Steel Co', [
            $this->inv('S-1', '05-09-2026', 1000),
            $this->inv('S-1-DUP', '05-09-2026', 1000), // same value and date: would be a probable match
        ])]), null, null);

        $this->assertSame($bill->id, $this->line($import, 'S-1')->vendor_bill_id);
        $this->assertSame(Gstr2bLine::MISSING_IN_BOOKS, $this->line($import, 'S-1-DUP')->match_status);
    }

    public function test_reupload_of_same_period_replaces_and_rematch_picks_up_new_bills(): void
    {
        $json = $this->portalJson([$this->supplier(self::STEEL, 'Steel Co', [$this->inv('S-1', '05-09-2026', 1000)])]);

        $first = $this->service()->import($json, 'a.json', null);
        $this->assertSame(Gstr2bLine::MISSING_IN_BOOKS, $this->line($first, 'S-1')->match_status);

        $second = $this->service()->import($json, 'b.json', null);
        $this->assertSame(1, Gstr2bImport::count());
        $this->assertSame(1, Gstr2bLine::count());
        $this->assertNull(Gstr2bImport::find($first->id));

        $this->bill($this->steel, 'S-1', '2026-09-05', 1000);
        $second = $this->service()->match($second);
        $this->assertSame(Gstr2bLine::MATCHED, $this->line($second, 'S-1')->match_status);
        $this->assertSame(1, $second->summary['matched']['count']);
    }

    public function test_file_for_another_gstin_is_rejected(): void
    {
        Company::create(['company_name' => 'Acme Pvt Ltd', 'gst_number' => '27AAACA1111A1Z5']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('belongs to GSTIN 24AAACZ0000Z1Z9');

        $this->service()->import($this->portalJson([], [], '24AAACZ0000Z1Z9'), null, null);
    }

    public function test_upload_and_screens_over_http(): void
    {
        $bill = $this->bill($this->steel, 'S-1', '2026-09-05', 1000);
        $this->bill($this->paper, 'PM-5', '2026-09-15', 300);

        $file = UploadedFile::fake()->createWithContent('returns_r2b_092026.json', $this->portalJson([
            $this->supplier(self::STEEL, 'Steel Co', [$this->inv('S-1', '05-09-2026', 1000), $this->inv('S-2', '06-09-2026', 50)]),
        ]));

        $response = $this->actingAs($this->accountant)->withHeader('X-Tenant', 'acme')
            ->post(route('accounting.gst-returns.gstr2b.store'), ['file' => $file]);

        $import = Gstr2bImport::firstOrFail();
        $response->assertRedirect(route('accounting.gst-returns.gstr2b.show', $import->id))->assertSessionHas('success');
        $this->assertSame($this->accountant->id, $import->imported_by);

        $this->get(route('accounting.gst-returns.gstr2b.index'))->assertOk()->assertSee('Sep 2026')->assertSee('Upload GSTR-2B');

        $this->get(route('accounting.gst-returns.gstr2b.show', $import->id))->assertOk()
            ->assertSee('Steel Co')->assertSee($bill->bill_number)->assertSee('No bill booked for this invoice.');
        $this->get(route('accounting.gst-returns.gstr2b.show', ['import' => $import->id, 'status' => 'books_only']))->assertOk()
            ->assertSee('PM-5');
        $this->get(route('accounting.gst-returns.gstr2b.show', ['import' => $import->id, 'status' => 'matched', 'q' => 'S-1']))->assertOk()
            ->assertSee($bill->bill_number);

        $this->post(route('accounting.gst-returns.gstr2b.rematch', $import->id))->assertRedirect()->assertSessionHas('success');
        $this->delete(route('accounting.gst-returns.gstr2b.destroy', $import->id))->assertRedirect(route('accounting.gst-returns.gstr2b.index'));
        $this->assertSame(0, Gstr2bImport::count());
        $this->assertSame(0, Gstr2bLine::count());
    }

    public function test_bad_upload_shows_error(): void
    {
        $file = UploadedFile::fake()->createWithContent('gstr1.json', json_encode(['gstin' => 'X', 'fp' => '092026', 'b2b' => []]));

        $this->actingAs($this->accountant)->withHeader('X-Tenant', 'acme')
            ->from(route('accounting.gst-returns.gstr2b.index'))
            ->post(route('accounting.gst-returns.gstr2b.store'), ['file' => $file])
            ->assertRedirect(route('accounting.gst-returns.gstr2b.index'))
            ->assertSessionHas('error');

        $this->assertSame(0, Gstr2bImport::count());
    }

    public function test_permissions(): void
    {
        $auditor = $this->makeUser('Avi Auditor', 'avi@acme.test', 'auditor');
        $sales = $this->makeUser('Sam Sales', 'sam@acme.test', 'sales_executive');
        $import = $this->service()->import($this->portalJson([]), null, null);

        $this->actingAs($auditor)->withHeader('X-Tenant', 'acme')->get(route('accounting.gst-returns.gstr2b.index'))
            ->assertOk()->assertDontSee('Upload GSTR-2B');
        $this->actingAs($auditor)->withHeader('X-Tenant', 'acme')->get(route('accounting.gst-returns.gstr2b.show', $import->id))->assertOk();
        $this->actingAs($auditor)->withHeader('X-Tenant', 'acme')->post(route('accounting.gst-returns.gstr2b.rematch', $import->id))->assertForbidden();
        $this->actingAs($auditor)->withHeader('X-Tenant', 'acme')->post(route('accounting.gst-returns.gstr2b.store'), [])->assertForbidden();

        $this->actingAs($sales)->withHeader('X-Tenant', 'acme')->get(route('accounting.gst-returns.gstr2b.index'))->assertForbidden();
    }
}
