<?php

namespace App\Console\Commands;

use App\Core\Tenant\TenantContext;
use App\Domains\Accounting\FixedAssets\Models\AssetDepreciationSchedule;
use App\Domains\Accounting\FixedAssets\Models\AssetDisposal;
use App\Domains\Accounting\FixedAssets\Models\AssetWriteOff;
use App\Domains\Accounting\FixedAssets\Services\AssetDisposalService;
use App\Domains\Accounting\FixedAssets\Support\AssetLedgers;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\JournalService;
use App\Domains\Accounting\Services\SystemAccountService;
use App\Domains\Accounting\Support\SystemAccount;
use App\Domains\HRMS\Models\Asset;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * One-time (safe to re-run) repair for fixed-asset entries posted before the
 * 2026-10 fixes, in this order:
 *  1. depreciation posted for a month after the asset was sold / written off
 *     -> journal reversed on its own date, schedule removed;
 *  2. sales whose GST was booked as a loss instead of being received
 *     -> journal reversed and re-posted with the corrected figures;
 *  3. revaluation reserve left behind on assets that have left the books
 *     -> moved to Reserves & Surplus on the disposal / write-off date.
 */
class RepairAssetPostings extends Command
{
    protected $signature = 'accounting:repair-asset-postings
                            {--tenant= : Only this tenant id}
                            {--dry-run : Report what would be corrected without changing anything}';

    protected $description = 'Correct fixed-asset journals posted before the disposal/depreciation fixes';

    private int $failures = 0;

    public function handle(JournalService $journals, AssetDisposalService $disposals, AssetLedgers $ledgers): int
    {
        $tenants = Tenant::query()
            ->when($this->option('tenant'), fn ($q, $id) => $q->whereKey((int) $id))
            ->get();

        foreach ($tenants as $tenant) {
            app(TenantContext::class)->set($tenant);
            $this->line("Tenant {$tenant->id} ({$tenant->name})");

            $this->depreciationAfterRetirement($journals);
            $reposted = $this->disposalsWithGstAsLoss($journals, $disposals);
            $this->unrealisedReserves($journals, $ledgers, $reposted);
        }

        if ($this->failures > 0) {
            $this->warn("{$this->failures} item(s) could not be corrected; fix the cause shown and run the command again.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{date: Carbon, journal_id: int}> asset id => when and by which journal it left the books */
    private function retirements(): array
    {
        $out = [];

        foreach (AssetDisposal::where('status', AssetDisposal::STATUS_POSTED)->whereNotNull('journal_id')->get(['asset_id', 'disposal_date', 'journal_id']) as $d) {
            $out[$d->asset_id] = ['date' => Carbon::parse($d->disposal_date), 'journal_id' => (int) $d->journal_id];
        }
        foreach (AssetWriteOff::where('status', AssetWriteOff::STATUS_POSTED)->whereNotNull('journal_id')->get(['asset_id', 'write_off_date', 'journal_id']) as $w) {
            $out[$w->asset_id] = ['date' => Carbon::parse($w->write_off_date), 'journal_id' => (int) $w->journal_id];
        }

        return $out;
    }

    private function depreciationAfterRetirement(JournalService $journals): void
    {
        foreach ($this->retirements() as $assetId => $retirement) {
            $retiredOn = $retirement['date'];
            // Posted after the sale / write-off entry: the disposal had already
            // cleared accumulated depreciation, so this one has no asset behind it.
            $schedules = AssetDepreciationSchedule::where('asset_id', $assetId)
                ->where('status', AssetDepreciationSchedule::STATUS_POSTED)
                ->where('journal_id', '>', $retirement['journal_id'])
                ->get();

            foreach ($schedules as $schedule) {
                $label = "depreciation {$schedule->period_month}/{$schedule->period_year} of asset #{$assetId} (after it left the books on {$retiredOn->toDateString()})";

                if ($this->option('dry-run')) {
                    $this->line("  would reverse {$label}: " . number_format((float) $schedule->depreciation_amount, 2));
                    continue;
                }

                $this->attempt($label, function () use ($journals, $schedule, $assetId) {
                    $journal = Journal::find($schedule->journal_id);
                    if ($journal && $journal->status === Journal::STATUS_POSTED) {
                        $journals->reverseOnOriginalDate($journal->id, 'Depreciation posted after the asset was sold / written off');
                    }

                    $asset = Asset::query()->lockForUpdate()->find($assetId);
                    $asset?->update(['accumulated_depreciation' => max(0, round((float) $asset->accumulated_depreciation - (float) $schedule->depreciation_amount, 2)), 'book_value' => 0]);
                    $schedule->delete();
                });
            }
        }
    }

    /** @return list<int> disposal ids re-posted here (their new journal already realises the reserve) */
    private function disposalsWithGstAsLoss(JournalService $journals, AssetDisposalService $disposals): array
    {
        $reposted = [];

        $candidates = AssetDisposal::where('status', AssetDisposal::STATUS_POSTED)
            ->where('tax_amount', '>', 0)
            ->whereNotNull('journal_id')
            ->get();

        foreach ($candidates as $disposal) {
            // The old rule took the GST off the price: gain = proceeds - tax - NBV.
            $oldGain = round((float) $disposal->sale_proceeds - (float) $disposal->tax_amount - (float) $disposal->net_book_value, 2);
            if (abs((float) $disposal->gain_loss_amount - $oldGain) >= 0.01) {
                continue;
            }

            $newGain = round((float) $disposal->sale_proceeds - (float) $disposal->net_book_value, 2);
            $label = "disposal #{$disposal->id} of asset #{$disposal->asset_id}: gain/(loss) " . number_format($oldGain, 2) . ' -> ' . number_format($newGain, 2);

            if ($this->option('dry-run')) {
                $this->line("  would re-post {$label}");
                $reposted[] = $disposal->id;
                continue;
            }

            $this->attempt($label, function () use ($journals, $disposals, $disposal, $newGain, &$reposted) {
                $old = Journal::find($disposal->journal_id);
                if ($old && $old->status === Journal::STATUS_POSTED) {
                    $journals->reverseOnOriginalDate($old->id, 'GST on asset sale was booked as a loss');
                }

                $disposal->update(['gain_loss_amount' => $newGain, 'gst_type' => $disposal->gst_type ?? AssetLedgers::GST_INTRA]);
                $asset = Asset::find($disposal->asset_id);

                $journal = $journals->post($disposals->postingLines($disposal->fresh(), $asset), [
                    'tenant_id' => $asset->tenant_id,
                    'company_id' => $asset->company_id,
                    'branch_id' => $asset->branch_id,
                    'journal_date' => $disposal->disposal_date,
                    'source' => Journal::SOURCE_FIXED_ASSETS,
                    'reference_type' => 'asset_disposal',
                    'reference_id' => $disposal->id,
                    'memo' => "Disposal ({$disposal->disposal_type}) - {$asset->asset_code} (corrected)",
                ]);

                $disposal->update(['journal_id' => $journal->id]);
                $reposted[] = $disposal->id;
            });
        }

        return $reposted;
    }

    /** @param list<int> $reposted */
    private function unrealisedReserves(JournalService $journals, AssetLedgers $ledgers, array $reposted): void
    {
        $reserve = app(SystemAccountService::class)->get(SystemAccount::REVALUATION_RESERVE, (int) tenant_id());
        if ($reserve === null) {
            return;
        }

        $retirements = collect()
            ->merge(AssetDisposal::where('status', AssetDisposal::STATUS_POSTED)->whereNotIn('id', $reposted)->get()
                ->map(fn ($d) => ['asset_id' => $d->asset_id, 'date' => $d->disposal_date, 'journal_id' => $d->journal_id, 'ref' => "disposal #{$d->id}"]))
            ->merge(AssetWriteOff::where('status', AssetWriteOff::STATUS_POSTED)->get()
                ->map(fn ($w) => ['asset_id' => $w->asset_id, 'date' => $w->write_off_date, 'journal_id' => $w->journal_id, 'ref' => "write-off #{$w->id}"]));

        foreach ($retirements as $r) {
            $alreadyRealised = JournalEntry::where('chart_of_account_id', $reserve->id)->where('debit', '>', 0)
                ->where(fn ($q) => $q->where('journal_id', $r['journal_id'])
                    ->orWhereHas('journal', fn ($j) => $j->where('reference_type', 'asset_reserve_transfer')->where('reference_id', $r['asset_id'])->where('status', Journal::STATUS_POSTED)))
                ->exists();

            $asset = Asset::find($r['asset_id']);
            $lines = $asset ? $ledgers->reserveTransferLines($asset) : [];

            if ($alreadyRealised || $lines === []) {
                continue;
            }

            $label = "revaluation reserve of asset #{$r['asset_id']} ({$r['ref']}): " . number_format((float) $lines[0]['debit'], 2);

            if ($this->option('dry-run')) {
                $this->line("  would move {$label} to Reserves & Surplus");
                continue;
            }

            $this->attempt($label, fn () => $journals->postOnce($lines, [
                'tenant_id' => $asset->tenant_id,
                'company_id' => $asset->company_id,
                'branch_id' => $asset->branch_id,
                'journal_date' => $r['date'],
                'source' => Journal::SOURCE_FIXED_ASSETS,
                'reference_type' => 'asset_reserve_transfer',
                'reference_id' => $asset->id,
                'memo' => "Revaluation reserve realised - {$asset->asset_code}",
            ]));
        }
    }

    private function attempt(string $label, callable $work): void
    {
        try {
            DB::transaction($work);
            $this->line("  corrected {$label}");
        } catch (Throwable $e) {
            $this->failures++;
            $this->error("  {$label}: {$e->getMessage()}");
        }
    }
}
