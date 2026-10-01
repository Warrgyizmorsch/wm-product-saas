<?php

namespace Database\Seeders;

use App\Core\Branch\BranchContext;
use App\Core\Company\CompanyContext;
use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\Services\Gst\Gstr1ReturnService;
use App\Domains\Accounting\Services\Gst\Gstr2bReconciliationService;
use App\Domains\Inventory\Models\Product;
use App\Domains\Inventory\Models\Vendor;
use App\Domains\Purchase\Models\VendorBill;
use App\Domains\Purchase\Services\VendorBillService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Demo data for Accounting → GSTR-2B Reconciliation.
 *
 * Books five purchase bills dated last month and builds a portal-shaped
 * GSTR-2B JSON for the same month that disagrees with the books in every way
 * the screen can show, then uploads it:
 *
 *   SGP/1101       Shree Ganesh Packaging   in both, same amounts           → Matched
 *   SGP/1102       Shree Ganesh Packaging   2B taxable 5,200, books 5,000   → Mismatch
 *   DE-2026-0456   Delhi Electricals        in both, IGST                   → Matched
 *   DE-2026-0457   Delhi Electricals        booked as "DE/457", a day apart → Mismatch (number differs)
 *   OL-889         Om Logistics             booked, vendor hasn't filed     → Not in 2B
 *   SGP/1105       Shree Ganesh Packaging   in 2B, never booked             → Not in your books
 *   RCS-77         Rapid Courier Services   in 2B, vendor not in books      → Not in your books
 *   CN-SGP-12      Shree Ganesh Packaging   credit note                     → Credit / debit notes
 *
 * The JSON is also saved to storage/app/demo/ so it can be uploaded again by hand.
 * Idempotent: bills are marked [DEMO-2B] and reused; the 2B upload is replaced.
 *
 *   php artisan db:seed --class=Gstr2bDemoSeeder
 */
class Gstr2bDemoSeeder extends Seeder
{
    private const MARK = '[DEMO-2B]';

    private const VENDORS = [
        'ganesh' => ['Shree Ganesh Packaging', '27AAGFS1234K1Z3', 'cgst_sgst'],
        'delhi' => ['Delhi Electricals Pvt Ltd', '07AAACD5678M1Z9', 'igst'],
        'om' => ['Om Logistics', '24AAKFO9012P1Z7', 'igst'],
    ];

    public function run(): void
    {
        $tenant = Tenant::where('slug', config('tenancy.local_fallback_slug', 'demo'))->first()
            ?? Tenant::where('slug', 'demo')->first()
            ?? Tenant::first();

        if (! $tenant) {
            $this->command?->error('No tenant found — aborting.');

            return;
        }

        app(TenantContext::class)->set($tenant);
        [$company, $branch] = $this->targetCompanyAndBranch($tenant->id);

        if (! $company || ! $branch) {
            $this->command?->error('Tenant has no company/branch — aborting.');

            return;
        }

        app(CompanyContext::class)->set($company);
        app(BranchContext::class)->set($branch);
        $this->command?->info("Using company \"{$company->company_name}\" / branch \"{$branch->name}\". (Set DEMO_BRANCH=<branch name> to choose another.)");

        $month = now()->subMonthNoOverflow()->startOfMonth();
        $period = $month->format('mY');
        $day = fn (int $d) => $month->copy()->day($d);

        $scope = ['tenant_id' => $tenant->id, 'company_id' => $company->id, 'branch_id' => $branch->id];

        $vendors = [];
        foreach (self::VENDORS as $key => [$name, $gstin]) {
            $vendor = Vendor::firstOrCreate(['tenant_id' => $tenant->id, 'name' => $name], $scope + ['status' => 'active', 'gstin' => $gstin]);
            if (! $vendor->gstin) {
                $vendor->forceFill(['gstin' => $gstin])->save();
            }
            $vendors[$key] = $vendor;
        }

        $product = Product::firstOrCreate(
            ['tenant_id' => $tenant->id, 'sku' => 'DEMO-2B-SUPPLY'],
            $scope + ['name' => 'Office & Packing Supplies', 'type' => 'consumable', 'item_type' => 'Goods', 'status' => 'active', 'unit_cost' => 100]
        );

        // [vendor, invoice no in books, day, taxable]
        $books = [
            ['ganesh', 'SGP/1101', 3, 10000],
            ['ganesh', 'SGP/1102', 7, 5000],
            ['delhi', 'DE-2026-0456', 9, 24000],
            ['delhi', 'DE/457', 14, 8000],
            ['om', 'OL-889', 18, 3500],
        ];

        $existing = VendorBill::withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('notes', 'like', self::MARK . '%')->pluck('id');

        if ($existing->isNotEmpty()) {
            $this->moveBills($existing->all(), $company->id, $branch->id);
            $this->command?->warn('GSTR-2B demo bills already existed — reusing them (moved into this company/branch).');
        } else {
            $buyer = User::firstOrCreate(
                ['email' => 'demo.buyer@example.com'],
                ['tenant_id' => $tenant->id, 'name' => 'Demo Buyer', 'password' => bcrypt(str()->random(32))]
            );
            Auth::login($buyer);

            foreach ($books as [$key, $invoiceNo, $d, $taxable]) {
                $bill = app(VendorBillService::class)->storeBill([
                    'vendor_id' => $vendors[$key]->id,
                    'vendor_invoice_number' => $invoiceNo,
                    'bill_date' => $day($d)->toDateString(),
                    'due_date' => $day($d)->addDays(30)->toDateString(),
                    'gst_type' => self::VENDORS[$key][2],
                    'tax_type' => 'item_wise_tax',
                    'notes' => self::MARK . ' GSTR-2B reconciliation demo.',
                    'items' => [[
                        'product_id' => $product->id,
                        'quantity' => $taxable / 100,
                        'unit_price' => 100,
                        'tax_rate' => 18,
                    ]],
                ], $tenant->id);

                $this->command?->line(sprintf('  %s  %-14s  %s  %s', $bill->bill_number, $invoiceNo, $bill->bill_date->format('d-m-Y'), number_format((float) $bill->grand_total, 2)));
            }

            Auth::logout();
        }

        // ---- The portal side -------------------------------------------------
        $ganesh = [
            $this->inv('SGP/1101', $day(3), 10000, false),
            $this->inv('SGP/1102', $day(7), 5200, false),
            $this->inv('SGP/1105', $day(22), 4000, false),
        ];
        $json = [
            'chksum' => 'demo',
            'data' => [
                'gstin' => app(Gstr1ReturnService::class)->seller()['gstin'] ?? '27AAACD0000D1Z5',
                'rtnprd' => $period,
                'version' => '1.0',
                'gendt' => $month->copy()->addMonthNoOverflow()->day(14)->format('d-m-Y'),
                'docdata' => [
                    'b2b' => [
                        $this->supplier('ganesh', $period, $month, $ganesh),
                        $this->supplier('delhi', $period, $month, [
                            $this->inv('DE-2026-0456', $day(9), 24000, true),
                            $this->inv('DE-2026-0457', $day(15), 8000, true),
                        ]),
                        [
                            'ctin' => '09AAACR4321Q1Z1', 'trdnm' => 'Rapid Courier Services',
                            'supfildt' => $month->copy()->addMonthNoOverflow()->day(11)->format('d-m-Y'), 'supprd' => $period,
                            'inv' => [$this->inv('RCS-77', $day(25), 1500, true)],
                        ],
                    ],
                    'cdnr' => [
                        $this->supplier('ganesh', $period, $month, [[
                            'ntnum' => 'CN-SGP-12', 'typ' => 'C', 'suptyp' => 'R', 'dt' => $day(27)->format('d-m-Y'),
                            'val' => 590, 'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y', 'rsn' => '',
                            'items' => [['num' => 1, 'rt' => 18, 'txval' => 500, 'igst' => 0, 'cgst' => 45, 'sgst' => 45, 'cess' => 0]],
                        ]], 'nt'),
                    ],
                ],
            ],
        ];

        $encoded = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $dir = storage_path('app/demo');
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $path = $dir . "/GSTR2B_demo_{$period}.json";
        file_put_contents($path, $encoded);

        $import = app(Gstr2bReconciliationService::class)->import($encoded, basename($path), null);
        $s = $import->summary;

        $this->command?->info(sprintf(
            'GSTR-2B %s uploaded: %d matched, %d mismatch, %d not in books, %d not in 2B, %d note(s).',
            $import->periodLabel(), $s['matched']['count'], $s['mismatch']['count'], $s['missing_in_books']['count'],
            $s['books_only']['count'], $s['note']['count']
        ));
        $this->command?->line("  Sample file: {$path}");
        $this->command?->info('Open Accounting → GSTR-2B Reconciliation.');
    }

    private function supplier(string $key, string $period, Carbon $month, array $docs, string $docKey = 'inv'): array
    {
        [$name, $gstin] = self::VENDORS[$key];

        return [
            'ctin' => $gstin,
            'trdnm' => strtoupper($name),
            'supfildt' => $month->copy()->addMonthNoOverflow()->day(11)->format('d-m-Y'),
            'supprd' => $period,
            $docKey => $docs,
        ];
    }

    private function inv(string $number, Carbon $date, float $taxable, bool $igst): array
    {
        $tax = round($taxable * 0.18, 2);

        return [
            'inum' => $number, 'typ' => 'R', 'dt' => $date->format('d-m-Y'), 'val' => $taxable + $tax,
            'pos' => '27', 'rev' => 'N', 'itcavl' => 'Y', 'rsn' => '', 'diffprcnt' => 1, 'srctyp' => 'Upload',
            'items' => [[
                'num' => 1, 'rt' => 18, 'txval' => $taxable,
                'igst' => $igst ? $tax : 0, 'cgst' => $igst ? 0 : $tax / 2, 'sgst' => $igst ? 0 : $tax / 2, 'cess' => 0,
            ]],
        ];
    }

    /** Move the demo bills (and their journals) into a company/branch so they're visible there. */
    private function moveBills(array $billIds, int $companyId, int $branchId): void
    {
        $journalIds = DB::table('journals')->where('reference_type', 'vendor_bill')->whereIn('reference_id', $billIds)->pluck('id')->all();

        foreach ([['vendor_bills', 'id', $billIds], ['vendor_bill_items', 'vendor_bill_id', $billIds], ['journals', 'id', $journalIds], ['journal_entries', 'journal_id', $journalIds]] as [$table, $key, $ids]) {
            $values = array_filter([
                'company_id' => \Illuminate\Support\Facades\Schema::hasColumn($table, 'company_id') ? $companyId : null,
                'branch_id' => \Illuminate\Support\Facades\Schema::hasColumn($table, 'branch_id') ? $branchId : null,
            ]);
            if ($values !== [] && $ids !== []) {
                DB::table($table)->whereIn($key, $ids)->update($values);
            }
        }
    }

    /**
     * Same rule as ThreeWayMatchDemoSeeder: DEMO_BRANCH (name or id) if given,
     * else the demo admin's own branch, else the tenant's default branch.
     */
    private function targetCompanyAndBranch(int $tenantId): array
    {
        $companies = \App\Domains\HRMS\Models\Company::withoutGlobalScopes()->where('tenant_id', $tenantId);
        $branches = fn () => \App\Domains\HRMS\Models\Branch::withoutGlobalScopes()->where('tenant_id', $tenantId);

        $wanted = trim((string) env('DEMO_BRANCH', ''));
        $branch = null;
        if ($wanted !== '') {
            $branch = ctype_digit($wanted)
                ? $branches()->whereKey((int) $wanted)->first()
                : ($branches()->where('name', $wanted)->first() ?? $branches()->where('name', 'like', "%{$wanted}%")->first());

            if (! $branch) {
                $this->command?->warn("No branch matches DEMO_BRANCH=\"{$wanted}\". Branches: " . $branches()->pluck('name')->implode(', '));
            }
        }

        if (! $branch) {
            $admin = User::withoutGlobalScopes()->where('tenant_id', $tenantId)->where('email', 'admin@example.com')->first()
                ?? User::withoutGlobalScopes()->where('tenant_id', $tenantId)->orderBy('id')->first();
            $branch = $admin?->branch_id ? $branches()->whereKey($admin->branch_id)->first() : null;
        }

        $branch ??= $branches()->where('is_default', true)->first() ?? $branches()->orderBy('id')->first();
        $company = $branch?->company_id ? (clone $companies)->whereKey($branch->company_id)->first() : (clone $companies)->first();

        return [$company, $branch];
    }
}
