<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankStatementLayout;
use App\Domains\Accounting\Models\BankStatementUpload;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Services\BankReconciliationService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\StatementExtraction\StatementNeedsMappingException;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Real bank exports: banner rows above the header, decoration rows, summary
 * blocks, Dr/Cr columns, running balances — and a one-time manual mapping
 * that is remembered per bank account.
 */
class BankStatementLayoutImportTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private ChartOfAccount $bank;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $this->tenant = Tenant::create(['name' => 'Layout Tenant', 'slug' => 'layout-tenant', 'status' => 'active', 'plan' => 'enterprise']);
        $this->seed(RbacSeeder::class);
        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);
        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->accountant = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'acc@layout.test', 'password' => bcrypt('password')]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->accountant->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);
        $this->accountant->forceFill(['role_id' => $role->id])->save();

        $this->bank = ChartOfAccount::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('code', '1020')->firstOrFail();
        $this->actingAs($this->accountant);
    }

    #[Test]
    public function an_hdfc_export_with_banner_rows_imports_as_downloaded(): void
    {
        $rec = $this->rec();

        $csv = implode("\n", [
            'HDFC BANK Ltd.,,,,,,',
            'M/S. DEMO TENANT PVT LTD,,,,,,',
            'Account No :50200012345678,,,,,,',
            ',,,,,,',
            'Date,Narration,Chq./Ref.No.,Value Dt,Withdrawal Amt.,Deposit Amt.,Closing Balance',
            '********,********,********,********,********,********,********',
            $this->dmy(2) . ',NEFT CR-GREENFIELD,UTR2609020001,' . $this->dmy(2) . ',,"50,000.00","1,50,000.00"',
            $this->dmy(4) . ',CHQ PAID-ABC SUPPLIERS,0000000451,' . $this->dmy(4) . ',"12,000.00",,"1,38,000.00"',
            ',,,,,,',
            'STATEMENT SUMMARY :-,,,,,,',
            'Opening Balance,Dr Count,Cr Count,Debits,Credits,Closing Bal,',
            '"1,00,000.00",1,1,"12,000.00","50,000.00","1,38,000.00",',
        ]);

        $result = $this->service()->importStatement($rec, $this->file('Acct_Statement.csv', $csv));

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['invalid'], 'banner, asterisk and summary rows are not errors');
        $this->assertSame(0, $result['balance_mismatches']);

        $lines = $rec->statementLines()->orderBy('transaction_date')->get();
        $this->assertSame([50000.0, -12000.0], $lines->pluck('amount')->all());
        $this->assertSame('0000000451', $lines[1]->reference);
        $this->assertSame(138000.0, $lines[1]->balance);

        // Balances filled from the running balance column (they were 0/0).
        $rec->refresh();
        $this->assertSame(100000.0, $rec->opening_balance);
        $this->assertSame(138000.0, $rec->closing_balance);

        $this->assertSame(1, BankStatementLayout::count(), 'the detected layout is remembered for the account');
    }

    #[Test]
    public function an_sbi_style_excel_with_a_dr_cr_column_and_excel_dates_imports(): void
    {
        $rec = $this->rec();

        $book = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([
            ['State Bank of India'],
            ['Account Statement from 01 ' . Carbon::parse($this->d(1))->format('M Y')],
            [],
            ['Txn Date', 'Description', 'Ref No./Cheque No.', 'Amount (INR)', 'Dr / Cr', 'Balance (INR)'],
            [\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(Carbon::parse($this->d(3))), 'BY TRANSFER-UPI/RAO', 'UPI998877', 2500, 'CR', 7500],
            [\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(Carbon::parse($this->d(5))), 'ATM WDL', '', 1000, 'DR', 6500],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'sbi') . '.xlsx';
        (new Xlsx($book))->save($path);

        $result = $this->service()->importStatement($rec, new UploadedFile($path, 'sbi.xlsx', null, null, true));

        $this->assertSame(2, $result['imported']);
        $lines = $rec->statementLines()->orderBy('transaction_date')->get();
        $this->assertSame([$this->d(3), $this->d(5)], $lines->map(fn ($l) => $l->transaction_date->toDateString())->all());
        $this->assertSame([2500.0, -1000.0], $lines->pluck('amount')->all(), 'Dr/Cr column gives the direction');
    }

    #[Test]
    public function running_balance_mismatches_are_reported(): void
    {
        $rec = $this->rec(['opening_balance' => 0, 'closing_balance' => 1]);

        $csv = "Date,Narration,Withdrawal,Deposit,Balance\n"
            . "{$this->dmy(2)},A,,100.00,100.00\n"
            . "{$this->dmy(3)},B,30.00,,75.00\n"; // should be 70.00

        $result = $this->service()->importStatement($rec, $this->file('s.csv', $csv));

        $this->assertSame(1, $result['balance_mismatches']);
        $this->assertSame(1.0, $rec->refresh()->closing_balance, 'balances the user typed are not overwritten');
    }

    #[Test]
    public function an_unrecognised_layout_is_mapped_once_and_remembered(): void
    {
        $rec = $this->rec();
        $csv = "Report generated 30/09\nTxnDt,Info,Out,In\n{$this->dmy(2)},Salary credit,,5000\n{$this->dmy(3)},Rent,2000,\n";

        try {
            $this->service()->importStatement($rec, $this->file('odd.csv', $csv));
            $this->fail('An unrecognised layout was imported without mapping.');
        } catch (StatementNeedsMappingException $e) {
            $upload = $e->upload;
        }

        $this->assertSame(BankStatementUpload::STATUS_NEEDS_MAPPING, $upload->status);
        $this->assertSame('TxnDt', $upload->raw_response['preview'][1][0]);

        $result = $this->service()->applyMapping($rec, $upload->id, 1, ['date' => 0, 'description' => 1, 'withdrawal' => 2, 'deposit' => 3], $this->accountant->id);

        $this->assertSame(2, $result['imported']);
        $this->assertSame(BankStatementLayout::SOURCE_MANUAL, BankStatementLayout::sole()->source);

        // Next month's file in the same format imports straight away.
        $next = "Report generated 31/10\nTxnDt,Info,Out,In\n{$this->dmy(8)},Interest,,12\n";
        $this->assertSame(1, $this->service()->importStatement($rec, $this->file('odd2.csv', $next))['imported']);
    }

    #[Test]
    public function the_mapping_screen_works_over_http(): void
    {
        $rec = $this->rec();
        $csv = "Bank export\nTxnDt,Info,Out,In\n{$this->dmy(2)},Salary credit,,5000\n";

        $this->http()->post(route('accounting.bank-reconciliation.import', $rec), ['file' => $this->file('odd.csv', $csv)])
            ->assertRedirect(route('accounting.bank-reconciliation.mapping', [$rec, BankStatementUpload::sole()->id]));

        $upload = BankStatementUpload::sole();
        $this->http()->get(route('accounting.bank-reconciliation.mapping', [$rec, $upload->id]))->assertOk()->assertSee('TxnDt');

        $this->http()->post(route('accounting.bank-reconciliation.apply-mapping', [$rec, $upload->id]), [
            'header_row' => 1,
            'columns' => ['date' => 0, 'description' => 1, 'withdrawal' => 2, 'deposit' => 3, 'reference' => ''],
        ])->assertRedirect(route('accounting.bank-reconciliation.show', $rec))->assertSessionHas('success');

        $this->assertSame(1, $rec->statementLines()->count());
    }

    private function rec(array $overrides = []): BankReconciliation
    {
        return app(BankReconciliationService::class)->start($overrides + [
            'tenant_id' => $this->tenant->id,
            'chart_of_account_id' => $this->bank->id,
            'statement_date' => $this->d(31),
            'closing_balance' => 0,
        ]);
    }

    private function service(): BankReconciliationService
    {
        return app(BankReconciliationService::class);
    }

    private function d(int $day): string
    {
        return Carbon::create(now()->year, 3, 1)->addDays($day - 1)->toDateString();
    }

    private function dmy(int $day): string
    {
        return Carbon::parse($this->d($day))->format('d/m/y');
    }

    private function file(string $name, string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    private function http()
    {
        return $this->actingAs($this->accountant)->withHeader('X-Tenant', $this->tenant->slug);
    }
}
