<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\BankStatementUpload;
use App\Domains\Accounting\Services\BankReconciliationService;
use App\Domains\Accounting\Services\ChartOfAccountsService;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\StatementExtraction\StatementExtractionException;
use App\Domains\Accounting\Services\StatementExtraction\StatementExtractionProvider;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BankStatementExtractionTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private User $accountant;
    private BankReconciliation $reconciliation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->tenant = Tenant::create([
            'name' => 'PDF Bank Rec Tenant', 'slug' => 'pdf-bank-rec-tenant', 'status' => 'active', 'plan' => 'enterprise',
        ]);

        $this->seed(RbacSeeder::class);
        app(ChartOfAccountsService::class)->provisionDefaults($this->tenant->id);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY ' . now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->accountant = User::create([
            'tenant_id' => $this->tenant->id, 'name' => 'Accountant', 'email' => 'accountant@pdf.test', 'password' => bcrypt('password'),
        ]);
        $role = Role::query()->whereNull('tenant_id')->where('slug', 'accountant')->firstOrFail();
        UserRole::create(['user_id' => $this->accountant->id, 'role_id' => $role->id, 'tenant_id' => $this->tenant->id]);

        $bank = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', '1020')->firstOrFail();

        $this->reconciliation = app(BankReconciliationService::class)->start([
            'tenant_id' => $this->tenant->id,
            'chart_of_account_id' => $bank->id,
            'statement_date' => now()->toDateString(),
            'opening_balance' => 0,
            'closing_balance' => 0,
        ]);
    }

    private function as(User $user)
    {
        return $this->actingAs($user)->withHeader('X-Tenant', $this->tenant->slug);
    }

    public function test_successful_extraction_creates_matching_statement_lines_and_completes_the_upload(): void
    {
        $this->app->bind(StatementExtractionProvider::class, fn () => new class implements StatementExtractionProvider {
            public function extract(string $absolutePath, string $mimeType): array
            {
                return [
                    'lines' => [
                        ['date' => now()->toDateString(), 'description' => 'NEFT Credit', 'amount' => 5000.0, 'suggested_ledger' => 'Sales Revenue'],
                        ['date' => now()->toDateString(), 'description' => 'Vendor Debit', 'amount' => -1200.0, 'suggested_ledger' => null],
                    ],
                    'opening_balance' => 1000.0,
                    'closing_balance' => 4800.0,
                    'account_info' => ['bank' => 'Test Bank', 'account_no' => '1234567890'],
                    'raw' => ['transactions' => []],
                ];
            }
        });

        $file = UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf');

        $response = $this->as($this->accountant)->post(
            route('accounting.bank-reconciliation.import', $this->reconciliation),
            ['file' => $file]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->reconciliation->refresh();
        $this->assertCount(2, $this->reconciliation->statementLines);

        $upload = BankStatementUpload::where('bank_reconciliation_id', $this->reconciliation->id)->firstOrFail();
        $this->assertSame(BankStatementUpload::STATUS_COMPLETED, $upload->status);
        $this->assertSame(2, $upload->extracted_count);
        $this->assertSame('statement.pdf', $upload->original_filename);
        Storage::disk('local')->assertExists($upload->path);

        // Opening/closing balance were still 0/0 (untouched default) — pre-filled from the statement.
        $this->reconciliation->refresh();
        $this->assertEquals(1000.0, $this->reconciliation->opening_balance);
        $this->assertEquals(4800.0, $this->reconciliation->closing_balance);

        $creditLine = $this->reconciliation->statementLines()->where('amount', 5000.0)->firstOrFail();
        $this->assertSame('Sales Revenue', $creditLine->suggested_ledger);
    }

    public function test_extraction_does_not_overwrite_balances_the_user_already_entered(): void
    {
        $this->reconciliation->update(['opening_balance' => 500, 'closing_balance' => 999]);

        $this->app->bind(StatementExtractionProvider::class, fn () => new class implements StatementExtractionProvider {
            public function extract(string $absolutePath, string $mimeType): array
            {
                return [
                    'lines' => [['date' => now()->toDateString(), 'description' => 'Deposit', 'amount' => 100.0, 'suggested_ledger' => null]],
                    'opening_balance' => 1000.0,
                    'closing_balance' => 4800.0,
                    'account_info' => [],
                    'raw' => [],
                ];
            }
        });

        $file = UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf');
        $this->as($this->accountant)->post(
            route('accounting.bank-reconciliation.import', $this->reconciliation),
            ['file' => $file]
        );

        $this->reconciliation->refresh();
        $this->assertEquals(500, $this->reconciliation->opening_balance);
        $this->assertEquals(999, $this->reconciliation->closing_balance);
    }

    public function test_extraction_failure_marks_the_upload_failed_and_flashes_an_error(): void
    {
        $this->app->bind(StatementExtractionProvider::class, fn () => new class implements StatementExtractionProvider {
            public function extract(string $absolutePath, string $mimeType): array
            {
                throw new StatementExtractionException('Statement extraction API is not configured.');
            }
        });

        $file = UploadedFile::fake()->create('statement.pdf', 10, 'application/pdf');

        $response = $this->as($this->accountant)->post(
            route('accounting.bank-reconciliation.import', $this->reconciliation),
            ['file' => $file]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Statement extraction API is not configured.');

        $this->assertCount(0, $this->reconciliation->refresh()->statementLines);

        $upload = BankStatementUpload::where('bank_reconciliation_id', $this->reconciliation->id)->firstOrFail();
        $this->assertSame(BankStatementUpload::STATUS_FAILED, $upload->status);
        $this->assertSame('Statement extraction API is not configured.', $upload->error_message);
    }

    public function test_create_and_match_posts_a_balanced_journal_against_the_chosen_ledger_and_matches_it(): void
    {
        $expense = \App\Domains\Accounting\Models\ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)->where('code', '5900')->firstOrFail();

        $line = \App\Domains\Accounting\Models\BankStatementLine::create([
            'tenant_id' => $this->tenant->id,
            'bank_reconciliation_id' => $this->reconciliation->id,
            'transaction_date' => now()->toDateString(),
            'description' => 'Card purchase',
            'suggested_ledger' => 'Other Expense',
            'amount' => -750.0,
        ]);

        $response = $this->as($this->accountant)->post(
            route('accounting.bank-reconciliation.create-and-match', $this->reconciliation),
            ['statement_line_id' => $line->id, 'chart_of_account_id' => $expense->id]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $line->refresh();
        $this->assertTrue($line->is_matched);
        $this->assertNotNull($line->matched_journal_entry_id);

        $bankEntry = \App\Domains\Accounting\Models\JournalEntry::withoutGlobalScopes()->findOrFail($line->matched_journal_entry_id);
        $this->assertTrue($bankEntry->is_reconciled);
        $this->assertSame($this->reconciliation->chart_of_account_id, $bankEntry->chart_of_account_id);
        $this->assertEquals(750.0, $bankEntry->credit);

        $journal = $bankEntry->journal;
        $this->assertSame('bank_statement_line', $journal->reference_type);
        $this->assertSame($line->id, $journal->reference_id);
    }

    public function test_create_and_match_rejects_the_bank_account_itself_as_the_ledger(): void
    {
        $line = \App\Domains\Accounting\Models\BankStatementLine::create([
            'tenant_id' => $this->tenant->id,
            'bank_reconciliation_id' => $this->reconciliation->id,
            'transaction_date' => now()->toDateString(),
            'description' => 'Bad line',
            'amount' => 100.0,
        ]);

        $response = $this->as($this->accountant)->post(
            route('accounting.bank-reconciliation.create-and-match', $this->reconciliation),
            ['statement_line_id' => $line->id, 'chart_of_account_id' => $this->reconciliation->chart_of_account_id]
        );

        $response->assertSessionHas('error');
        $this->assertFalse($line->refresh()->is_matched);
    }

    public function test_csv_import_path_is_unaffected_by_the_new_pdf_branch(): void
    {
        $csv = "date,description,amount\n" . now()->toDateString() . ",Deposit,2500\n";
        $file = UploadedFile::fake()->createWithContent('statement.csv', $csv);

        $response = $this->as($this->accountant)->post(
            route('accounting.bank-reconciliation.import', $this->reconciliation),
            ['file' => $file]
        );

        $response->assertRedirect();
        $this->assertCount(1, $this->reconciliation->refresh()->statementLines);
        $this->assertSame(0, BankStatementUpload::where('bank_reconciliation_id', $this->reconciliation->id)->count());
    }
}
