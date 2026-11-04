<?php

namespace Tests\Feature\Accounting;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Models\ChartOfAccount;
use App\Domains\Accounting\Models\IntegrationAgentJob;
use App\Domains\Accounting\Models\IntegrationConflict;
use App\Domains\Accounting\Models\IntegrationConnection;
use App\Domains\Accounting\Models\IntegrationRef;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Services\FiscalPeriodService;
use App\Domains\Accounting\Services\Integration\Tally\TallyXmlBuilder;
use App\Domains\Accounting\Services\Integration\Tally\TallyXmlParser;
use App\Domains\Accounting\Services\JournalService;
use App\Models\Access\Role;
use App\Models\Access\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Tally adapter and relay protocol. The XML here is hand-written from Tally's
 * published samples, not captured from a real install: this proves our logic,
 * not Tally's behaviour.
 */
class AccountingIntegrationTallyTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/accounting/integrations';

    private Tenant $tenant;
    private User $owner;
    private IntegrationConnection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RbacSeeder::class);
        $this->tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'active', 'plan' => 'enterprise']);
        app(TenantContext::class)->set($this->tenant);

        app(FiscalPeriodService::class)->createFiscalYearWithMonthlyPeriods([
            'tenant_id' => $this->tenant->id,
            'name' => 'FY '.now()->year,
            'start_date' => now()->startOfYear()->toDateString(),
            'end_date' => now()->endOfYear()->toDateString(),
        ]);

        $this->owner = User::create(['tenant_id' => $this->tenant->id, 'name' => 'Owner', 'email' => 'o@acme.test', 'password' => bcrypt('password')]);
        UserRole::create([
            'user_id' => $this->owner->id,
            'role_id' => Role::query()->whereNull('tenant_id')->where('slug', 'tenant_owner')->firstOrFail()->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->connection = IntegrationConnection::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Tally HO',
            'product' => IntegrationConnection::PRODUCT_TALLY,
            'external_company' => 'Acme & Sons',
            'status' => IntegrationConnection::STATUS_ACTIVE,
            'settings' => ['auto_sync_minutes' => 0, 'sync_from' => now()->startOfMonth()->toDateString()],
        ]);
    }

    private function api(): static
    {
        Sanctum::actingAs($this->owner, ['*']);

        return $this->withHeader('X-Tenant', 'acme');
    }

    private function url(string $path): string
    {
        return self::BASE."/connections/{$this->connection->id}{$path}";
    }

    private function groupsXml(): string
    {
        return <<<'XML'
<ENVELOPE><BODY><DATA><COLLECTION>
<GROUP NAME="Sundry Debtors"><GUID>g-1</GUID><PARENT>Current Assets</PARENT></GROUP>
<GROUP NAME="Current Assets"><GUID>g-2</GUID><PARENT>Primary</PARENT></GROUP>
<GROUP NAME="North Debtors"><GUID>g-3</GUID><PARENT>Sundry Debtors</PARENT></GROUP>
<GROUP NAME="Indirect Expenses"><GUID>g-4</GUID><PARENT>Primary</PARENT></GROUP>
<GROUP NAME="Bank Accounts"><GUID>g-5</GUID><PARENT>Current Assets</PARENT></GROUP>
</COLLECTION></DATA></BODY></ENVELOPE>
XML;
    }

    private function ledgersXml(): string
    {
        return <<<'XML'
<ENVELOPE><BODY><DATA><COLLECTION>
<LEDGER NAME="HDFC Bank"><GUID>l-1</GUID><PARENT>Bank Accounts</PARENT></LEDGER>
<LEDGER NAME="Office Rent"><GUID>l-2</GUID><PARENT>Indirect Expenses</PARENT></LEDGER>
<LEDGER NAME="Ravi &amp; Co"><GUID>l-3</GUID><PARENT>North Debtors</PARENT></LEDGER>
<LEDGER NAME="Profit &amp; Loss A/c"><GUID>l-4</GUID></LEDGER>
</COLLECTION></DATA></BODY></ENVELOPE>
XML;
    }

    private function voucherXml(string $guid = 'v-1', string $amount = '1000.00', string $extra = '', string $type = 'Payment'): string
    {
        $date = now()->format('Ymd');

        return <<<XML
<VOUCHER VCHTYPE="{$type}" ACTION="Create"><GUID>{$guid}</GUID><DATE>{$date}</DATE>
<VOUCHERTYPENAME>{$type}</VOUCHERTYPENAME><VOUCHERNUMBER>{$guid}-no</VOUCHERNUMBER><NARRATION>Rent</NARRATION>{$extra}
<ALLLEDGERENTRIES.LIST><LEDGERNAME>Office Rent</LEDGERNAME><ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE><AMOUNT>-{$amount}</AMOUNT></ALLLEDGERENTRIES.LIST>
<ALLLEDGERENTRIES.LIST><LEDGERNAME>HDFC Bank</LEDGERNAME><ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE><AMOUNT>{$amount}</AMOUNT></ALLLEDGERENTRIES.LIST>
</VOUCHER>
XML;
    }

    private function dayBook(string ...$vouchers): string
    {
        return '<ENVELOPE><BODY><DATA><TALLYMESSAGE>'.implode('</TALLYMESSAGE><TALLYMESSAGE>', $vouchers).'</TALLYMESSAGE></DATA></BODY></ENVELOPE>';
    }

    private function accepted(int $created = 1): string
    {
        return "<RESPONSE><CREATED>{$created}</CREATED><ALTERED>0</ALTERED><IGNORED>0</IGNORED><ERRORS>0</ERRORS><LASTVCHID>4242</LASTVCHID></RESPONSE>";
    }

    /** Polls and answers every job, the way the relay does. */
    private function runRelay(callable $answer): array
    {
        $seen = [];

        for ($guard = 0; $guard < 20; $guard++) {
            $jobs = $this->api()->postJson($this->url('/tally/agent/poll'), ['limit' => 10, 'agent_version' => 'test'])->assertOk()->json('data');
            if ($jobs === []) {
                break;
            }

            foreach ($jobs as $job) {
                $seen[] = $job;
                $this->api()->postJson($this->url("/tally/agent/jobs/{$job['id']}/result"), ['response_body' => $answer($job)])->assertOk();
            }
        }

        return $seen;
    }

    private function answerPull(string $dayBook): callable
    {
        return fn (array $job) => match ($job['kind']) {
            IntegrationAgentJob::KIND_PULL_GROUPS => $this->groupsXml(),
            IntegrationAgentJob::KIND_PULL_LEDGERS => $this->ledgersXml(),
            IntegrationAgentJob::KIND_PULL_VOUCHERS => $dayBook,
            default => $this->accepted(),
        };
    }

    private function syncIn(string $dayBook): void
    {
        $this->api()->postJson($this->url('/tally/sync'), ['direction' => 'inbound', 'from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()])->assertOk();
        $this->runRelay($this->answerPull($dayBook));
    }

    private function imported()
    {
        return Journal::query()->withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->where('source', Journal::SOURCE_INTEGRATION);
    }

    // ── Parser ────────────────────────────────────────────────────────────

    public function test_parser_reads_debit_and_credit_from_the_amount_sign(): void
    {
        $vouchers = app(TallyXmlParser::class)->vouchers($this->dayBook($this->voucherXml()));

        $this->assertSame('payment', $vouchers[0]['voucher_type']);
        $this->assertSame(1000.0, $vouchers[0]['lines'][0]['debit']);
        $this->assertSame(1000.0, $vouchers[0]['lines'][1]['credit']);
        $this->assertSame(now()->toDateString(), $vouchers[0]['date']);
    }

    public function test_parser_finds_sales_ledger_inside_inventory_allocations(): void
    {
        $xml = $this->dayBook('<VOUCHER><GUID>s-1</GUID><DATE>20261101</DATE><VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>
            <ALLLEDGERENTRIES.LIST><LEDGERNAME>Ravi</LEDGERNAME><AMOUNT>-500.00</AMOUNT></ALLLEDGERENTRIES.LIST>
            <ALLINVENTORYENTRIES.LIST><STOCKITEMNAME>Widget</STOCKITEMNAME><ACCOUNTINGALLOCATIONS.LIST><LEDGERNAME>Sales</LEDGERNAME><AMOUNT>500.00</AMOUNT></ACCOUNTINGALLOCATIONS.LIST></ALLINVENTORYENTRIES.LIST>
            </VOUCHER>');

        $lines = app(TallyXmlParser::class)->vouchers($xml)[0]['lines'];

        $this->assertSame(['Ravi', 'Sales'], array_column($lines, 'ledger_name'));
        $this->assertSame(500.0, array_sum(array_column($lines, 'credit')));
    }

    public function test_parser_drops_optional_and_non_accounting_vouchers(): void
    {
        $xml = $this->dayBook(
            $this->voucherXml('a', '10.00', '', 'Sales Order'),
            $this->voucherXml('b', '10.00', '<ISOPTIONAL>Yes</ISOPTIONAL>'),
            $this->voucherXml('c'),
        );

        $this->assertSame(['c'], array_column(app(TallyXmlParser::class)->vouchers($xml), 'external_id'));
    }

    public function test_parser_refuses_external_entities(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(TallyXmlParser::class)->ledgers('<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><ENVELOPE><LEDGER NAME="&e;"/></ENVELOPE>');
    }

    public function test_parser_tolerates_control_character_references(): void
    {
        $rows = app(TallyXmlParser::class)->ledgers('<ENVELOPE><LEDGER NAME="A&#4; B"><GUID>x</GUID><PARENT>Sundry Debtors</PARENT></LEDGER></ENVELOPE>');

        $this->assertSame('A B', $rows[0]['name']);
    }

    public function test_import_response_reads_success_and_line_errors(): void
    {
        $parser = app(TallyXmlParser::class);

        $this->assertTrue($parser->importResponse($this->accepted())['ok']);
        $this->assertSame('4242', $parser->importResponse($this->accepted())['last_voucher_id']);

        $bad = $parser->importResponse('<ENVELOPE><STATUS>0</STATUS><LINEERROR>Voucher totals do not match!</LINEERROR></ENVELOPE>');
        $this->assertFalse($bad['ok']);
        $this->assertSame('Voucher totals do not match!', $bad['error']);

        $this->assertFalse($parser->importResponse('<RESPONSE><CREATED>0</CREATED><ERRORS>1</ERRORS></RESPONSE>')['ok']);
    }

    public function test_builder_escapes_names_and_signs_amounts_like_tallys_sample(): void
    {
        $xml = app(TallyXmlBuilder::class)->importVouchers($this->connection, [[
            'remote_id' => 'erp-1-9',
            'tally_type' => 'Payment',
            'date' => '2026-11-04',
            'number' => 'PAY-1',
            'narration' => 'Rent <Nov> & more',
            'lines' => [
                ['ledger_name' => 'Ravi & Co', 'debit' => 100.5, 'credit' => 0],
                ['ledger_name' => 'Bank', 'debit' => 0, 'credit' => 100.5],
            ],
        ]]);

        $root = simplexml_load_string($xml);
        $this->assertNotFalse($root, 'Must be well-formed XML.');
        $this->assertStringContainsString('<DATE>20261104</DATE>', $xml);
        $this->assertStringContainsString('<LEDGERNAME>Ravi &amp; Co</LEDGERNAME><ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE><AMOUNT>-100.50</AMOUNT>', $xml);
        $this->assertStringContainsString('<LEDGERNAME>Bank</LEDGERNAME><ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE><AMOUNT>100.50</AMOUNT>', $xml);
        $this->assertStringContainsString('<SVCURRENTCOMPANY>Acme &amp; Sons</SVCURRENTCOMPANY>', $xml);
    }

    public function test_what_we_build_reads_back_the_same_through_our_parser(): void
    {
        $lines = [
            ['ledger_name' => 'Office Rent', 'debit' => 250.75, 'credit' => 0.0],
            ['ledger_name' => 'HDFC Bank', 'debit' => 0.0, 'credit' => 250.75],
        ];
        $xml = app(TallyXmlBuilder::class)->importVouchers($this->connection, [[
            'remote_id' => 'erp-1-9', 'tally_type' => 'Payment', 'date' => '2026-11-04', 'number' => 'P', 'narration' => null, 'lines' => $lines,
        ]]);

        $this->assertSame($lines, app(TallyXmlParser::class)->vouchers($xml)[0]['lines']);
    }

    // ── Pull: Tally into the ERP ──────────────────────────────────────────

    public function test_full_pull_maps_groups_and_ledgers_and_posts_a_balanced_voucher(): void
    {
        $this->syncIn($this->dayBook($this->voucherXml()));

        $bank = ChartOfAccount::query()->where('name', 'HDFC Bank')->firstOrFail();
        $this->assertSame('asset', $bank->type);
        $this->assertTrue($bank->is_cash_or_bank);

        $this->assertSame('expense', ChartOfAccount::query()->where('name', 'Office Rent')->value('type'));

        // Under a custom group: the type comes from walking up to Sundry Debtors.
        $party = ChartOfAccount::query()->where('name', 'Ravi & Co')->firstOrFail();
        $this->assertSame('asset', $party->type);
        $this->assertNull($party->parent_id, 'Must not become a child of a control account.');

        $this->assertFalse(ChartOfAccount::query()->where('name', 'Profit & Loss A/c')->exists());

        $journal = $this->imported()->with('entries')->firstOrFail();
        $this->assertTrue($journal->isBalanced());
        $this->assertSame(1000.0, (float) $journal->total_debit);
    }

    public function test_pulling_the_same_day_book_twice_does_not_duplicate(): void
    {
        $this->syncIn($this->dayBook($this->voucherXml()));
        $accounts = ChartOfAccount::query()->count();

        $this->syncIn($this->dayBook($this->voucherXml()));

        $this->assertSame(1, $this->imported()->count());
        $this->assertSame($accounts, ChartOfAccount::query()->count());
    }

    public function test_altered_voucher_in_tally_replaces_the_entry(): void
    {
        $this->syncIn($this->dayBook($this->voucherXml()));
        $this->syncIn($this->dayBook($this->voucherXml('v-1', '1200.00')));

        $this->assertSame(1, $this->imported()->where('status', Journal::STATUS_REVERSED)->count());
        $this->assertSame(1, $this->imported()->where('status', Journal::STATUS_POSTED)->where('total_debit', 1200)->count());
    }

    public function test_cancelled_voucher_in_tally_is_reversed(): void
    {
        $this->syncIn($this->dayBook($this->voucherXml()));
        $this->syncIn($this->dayBook($this->voucherXml('v-1', '1000.00', '<ISCANCELLED>Yes</ISCANCELLED>')));

        $this->assertSame(1, $this->imported()->where('status', Journal::STATUS_REVERSED)->count());
    }

    public function test_voucher_gone_from_the_day_book_raises_a_conflict_and_is_not_reversed(): void
    {
        $this->syncIn($this->dayBook($this->voucherXml('v-1'), $this->voucherXml('v-2', '50.00')));
        $this->syncIn($this->dayBook($this->voucherXml('v-2', '50.00')));

        $conflict = IntegrationConflict::query()->where('reason', IntegrationConflict::REASON_MISSING_IN_SOURCE)->firstOrFail();
        $this->assertSame('v-1', $conflict->external_id);
        $this->assertSame(0, $this->imported()->where('status', Journal::STATUS_REVERSED)->count());

        // Apply incoming means: yes, it was deleted there, reverse it here.
        $this->api()->postJson($this->url("/conflicts/{$conflict->id}/resolve"), ['resolution' => 'apply_incoming'])->assertOk();
        $this->assertSame(1, $this->imported()->where('status', Journal::STATUS_REVERSED)->count());
    }

    public function test_empty_reply_is_not_treated_as_everything_being_deleted(): void
    {
        $this->syncIn($this->dayBook($this->voucherXml()));
        $this->syncIn('<ENVELOPE><BODY><DATA></DATA></BODY></ENVELOPE>');

        $this->assertSame(0, IntegrationConflict::query()->count());
    }

    public function test_custom_voucher_type_fails_visibly_until_mapped(): void
    {
        $this->syncIn($this->dayBook($this->voucherXml('v-9', '10.00', '', 'Bank Payment HDFC')));
        $this->assertSame(0, $this->imported()->count());

        $this->connection->update(['settings' => array_merge($this->connection->settings, ['voucher_type_map' => ['Bank Payment HDFC' => 'payment']])]);

        $this->syncIn($this->dayBook($this->voucherXml('v-9', '10.00', '', 'Bank Payment HDFC')));
        $this->assertSame(1, $this->imported()->count());
    }

    // ── Push: the ERP into Tally ──────────────────────────────────────────

    private function localJournal(float $amount = 70): Journal
    {
        $rent = ChartOfAccount::query()->firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => '5500'],
            ['name' => 'Rent ERP', 'type' => 'expense', 'normal_balance' => 'debit']
        );
        $bank = ChartOfAccount::query()->firstOrCreate(
            ['tenant_id' => $this->tenant->id, 'code' => '1020'],
            ['name' => 'Bank ERP', 'type' => 'asset', 'normal_balance' => 'debit', 'is_cash_or_bank' => true]
        );

        return app(JournalService::class)->post([
            ['chart_of_account_id' => $rent->id, 'debit' => $amount, 'credit' => 0],
            ['chart_of_account_id' => $bank->id, 'debit' => 0, 'credit' => $amount],
        ], ['tenant_id' => $this->tenant->id, 'memo' => 'Rent']);
    }

    private function push(callable $answer): array
    {
        $this->api()->postJson($this->url('/tally/sync'), ['direction' => 'outbound'])->assertOk();

        return $this->runRelay($answer);
    }

    public function test_push_creates_ledgers_first_then_one_job_per_voucher(): void
    {
        $journal = $this->localJournal();
        $this->localJournal(80);

        $jobs = $this->push(fn () => $this->accepted());

        $this->assertSame(
            [IntegrationAgentJob::KIND_PUSH_LEDGER, IntegrationAgentJob::KIND_PUSH_LEDGER, IntegrationAgentJob::KIND_PUSH_VOUCHER, IntegrationAgentJob::KIND_PUSH_VOUCHER],
            array_column($jobs, 'kind'),
        );
        $this->assertStringContainsString('<PARENT>Bank Accounts</PARENT>', implode('', array_column($jobs, 'request_xml')));
        $this->assertStringContainsString('REMOTEID="erp-'.$this->tenant->id.'-'.$journal->id.'"', $jobs[2]['request_xml']);

        $ref = IntegrationRef::query()->where('entity_type', 'voucher')->where('local_id', $journal->id)->firstOrFail();
        $this->assertSame(IntegrationRef::STATUS_SYNCED, $ref->status);
        $this->assertSame('4242', $ref->meta['tally_last_voucher_id']);
    }

    public function test_second_cycle_pushes_nothing_new(): void
    {
        $this->localJournal();
        $this->push(fn () => $this->accepted());

        $this->assertSame([], $this->push(fn () => $this->accepted()));
    }

    public function test_tally_refusal_is_logged_without_retrying_and_can_be_released(): void
    {
        $journal = $this->localJournal();

        $jobs = $this->push(fn (array $job) => $job['kind'] === IntegrationAgentJob::KIND_PUSH_VOUCHER
            ? '<ENVELOPE><STATUS>0</STATUS><LINEERROR>Ledger not found</LINEERROR></ENVELOPE>'
            : $this->accepted());

        $this->assertCount(3, $jobs, 'A refused voucher must not be sent again by itself.');

        $ref = IntegrationRef::query()->where('entity_type', 'voucher')->where('local_id', $journal->id)->firstOrFail();
        $this->assertSame(IntegrationRef::STATUS_FAILED, $ref->status);
        $this->assertSame('Ledger not found', $ref->last_error);

        $this->api()->postJson($this->url('/tally/retry-pushes'))->assertOk()->assertJsonPath('data.released', 1);

        $again = $this->push(fn () => $this->accepted());
        $this->assertSame([IntegrationAgentJob::KIND_PUSH_VOUCHER], array_column($again, 'kind'));
    }

    public function test_unreachable_tally_retries_then_fails(): void
    {
        $this->localJournal();
        $this->api()->postJson($this->url('/tally/sync'), ['direction' => 'outbound'])->assertOk();

        $job = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $jobs = $this->api()->postJson($this->url('/tally/agent/poll'), ['limit' => 1])->json('data');
            $job = $jobs[0];
            $this->api()->postJson($this->url("/tally/agent/jobs/{$job['id']}/result"), ['transport_error' => 'Connection refused'])->assertOk();
        }

        $this->assertSame(IntegrationAgentJob::STATUS_FAILED, IntegrationAgentJob::query()->find($job['id'])->status);
    }

    public function test_abandoned_lease_goes_back_to_the_queue(): void
    {
        $this->localJournal();
        $this->api()->postJson($this->url('/tally/sync'), ['direction' => 'outbound'])->assertOk();

        $first = $this->api()->postJson($this->url('/tally/agent/poll'), ['limit' => 1])->json('data.0.id');
        IntegrationAgentJob::query()->whereKey($first)->update(['leased_until' => now()->subMinute()]);

        $this->assertSame($first, $this->api()->postJson($this->url('/tally/agent/poll'), ['limit' => 1])->json('data.0.id'));
    }

    public function test_a_duplicate_result_is_ignored(): void
    {
        $this->localJournal();
        $this->api()->postJson($this->url('/tally/sync'), ['direction' => 'outbound'])->assertOk();
        $job = $this->api()->postJson($this->url('/tally/agent/poll'), ['limit' => 1])->json('data.0');

        $this->api()->postJson($this->url("/tally/agent/jobs/{$job['id']}/result"), ['response_body' => $this->accepted()])->assertOk();
        $this->api()->postJson($this->url("/tally/agent/jobs/{$job['id']}/result"), ['transport_error' => 'late'])
            ->assertOk()
            ->assertJsonPath('data.status', IntegrationAgentJob::STATUS_COMPLETED);
    }

    // ── Both directions together ──────────────────────────────────────────

    public function test_our_own_voucher_coming_back_from_tally_is_not_imported(): void
    {
        $journal = $this->localJournal();
        $this->push(fn () => $this->accepted());

        $remote = 'erp-'.$this->tenant->id.'-'.$journal->id;
        $date = now()->format('Ymd');
        $echo = '<VOUCHER REMOTEID="'.$remote.'" VCHTYPE="Journal"><GUID>tally-guid</GUID><DATE>'.$date.'</DATE><VOUCHERTYPENAME>Journal</VOUCHERTYPENAME>
            <ALLLEDGERENTRIES.LIST><LEDGERNAME>Rent ERP</LEDGERNAME><AMOUNT>-70.00</AMOUNT></ALLLEDGERENTRIES.LIST>
            <ALLLEDGERENTRIES.LIST><LEDGERNAME>Bank ERP</LEDGERNAME><AMOUNT>70.00</AMOUNT></ALLLEDGERENTRIES.LIST></VOUCHER>';

        $this->syncIn($this->dayBook($echo));

        $this->assertSame(0, $this->imported()->count());
    }

    public function test_echo_is_also_caught_by_number_and_date_when_remote_id_is_lost(): void
    {
        $journal = $this->localJournal();
        $this->push(fn () => $this->accepted());

        $date = now()->format('Ymd');
        $echo = '<VOUCHER VCHTYPE="Journal"><GUID>tally-guid</GUID><DATE>'.$date.'</DATE><VOUCHERTYPENAME>Journal</VOUCHERTYPENAME>
            <VOUCHERNUMBER>'.$journal->journal_number.'</VOUCHERNUMBER>
            <ALLLEDGERENTRIES.LIST><LEDGERNAME>Rent ERP</LEDGERNAME><AMOUNT>-70.00</AMOUNT></ALLLEDGERENTRIES.LIST>
            <ALLLEDGERENTRIES.LIST><LEDGERNAME>Bank ERP</LEDGERNAME><AMOUNT>70.00</AMOUNT></ALLLEDGERENTRIES.LIST></VOUCHER>';

        $this->syncIn($this->dayBook($echo));

        $this->assertSame(0, $this->imported()->count());
    }

    public function test_imported_voucher_is_never_pushed_back(): void
    {
        $this->syncIn($this->dayBook($this->voucherXml()));

        $jobs = $this->push(fn () => $this->accepted());

        $this->assertSame([], $jobs);
    }

    public function test_pushed_ledger_is_adopted_when_tally_returns_it_with_its_own_id(): void
    {
        $this->localJournal();
        $this->push(fn () => $this->accepted());
        $accounts = ChartOfAccount::query()->count();

        $this->api()->post($this->url('/tally/import-file'), [
            'entity' => 'ledgers',
            'file' => UploadedFile::fake()->createWithContent('l.xml', '<ENVELOPE><LEDGER NAME="Rent ERP"><GUID>tally-1</GUID><PARENT>Indirect Expenses</PARENT></LEDGER></ENVELOPE>'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $this->assertSame($accounts, ChartOfAccount::query()->count());
        $this->assertSame(1, IntegrationRef::query()->where('entity_type', 'ledger')->where('external_id', 'tally-1')->count());
    }

    // ── File route ────────────────────────────────────────────────────────

    public function test_file_import_and_export_work_without_the_relay(): void
    {
        $upload = function (string $entity, string $xml) {
            return $this->api()->post($this->url('/tally/import-file'), [
                'entity' => $entity,
                'file' => UploadedFile::fake()->createWithContent("{$entity}.xml", $xml),
            ], ['Accept' => 'application/json'])->assertCreated();
        };

        $upload('ledger-groups', $this->groupsXml());
        $upload('ledgers', $this->ledgersXml());
        $upload('vouchers', $this->dayBook($this->voucherXml()));
        $this->assertSame(1, $this->imported()->count());

        $journal = $this->localJournal();

        $ledgers = $this->api()->get($this->url('/tally/export-file?entity=ledgers'))->assertOk();
        $this->assertStringContainsString('<LEDGER NAME="Rent ERP"', $ledgers->getContent());

        $vouchers = $this->api()->get($this->url('/tally/export-file?entity=vouchers'))->assertOk();
        $this->assertStringContainsString('<VOUCHERTYPENAME>Journal</VOUCHERTYPENAME>', $vouchers->getContent());
        $this->assertSame((string) $journal->id, $vouchers->headers->get('X-Accounting-Local-Ids'));

        // Not marked sent until confirmed.
        $this->api()->postJson($this->url('/feed/vouchers/ack'), ['items' => [['local_id' => $journal->id]]])->assertOk();
        $this->assertSame('', (string) $this->api()->get($this->url('/tally/export-file?entity=vouchers'))->headers->get('X-Accounting-Local-Ids'));
    }

    public function test_malformed_upload_is_refused_cleanly(): void
    {
        $this->api()->post($this->url('/tally/import-file'), [
            'entity' => 'vouchers',
            'file' => UploadedFile::fake()->createWithContent('v.xml', '<ENVELOPE><broken'),
        ], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_tally_calls_are_refused_on_other_products(): void
    {
        $custom = IntegrationConnection::create(['tenant_id' => $this->tenant->id, 'name' => 'Custom', 'product' => 'custom', 'status' => 'active']);

        $this->api()->postJson(self::BASE."/connections/{$custom->id}/tally/sync")->assertStatus(422);
    }

    public function test_paused_connection_gets_no_jobs(): void
    {
        $this->localJournal();
        $this->connection->update(['status' => IntegrationConnection::STATUS_PAUSED]);

        $this->assertSame([], $this->api()->postJson($this->url('/tally/agent/poll'))->assertOk()->json('data'));
    }
}
