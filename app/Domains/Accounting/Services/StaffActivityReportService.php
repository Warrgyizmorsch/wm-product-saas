<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\BankReconciliation;
use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\JournalRepositoryInterface;
use App\Domains\Accounting\Support\VoucherType;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Who posted what: journals and vouchers per person and document type, with
 * reversals and completed bank reconciliations, for a date range. Journals
 * auto-posted without a signed-in user are grouped under "System".
 */
class StaffActivityReportService
{
    public const SYSTEM = 'system';

    public function __construct(
        private readonly JournalRepositoryInterface $journals,
    ) {
    }

    /**
     * Document columns in display order: plain journals first, then each voucher type.
     *
     * @return array<string, string>
     */
    public function documentTypes(): array
    {
        return ['journal' => 'Journals'] + collect(VoucherType::ALL)
            ->mapWithKeys(fn (string $type) => [$type => VoucherType::label($type).'s'])
            ->all();
    }

    /**
     * @return array{rows: list<array{key: string, name: string, counts: array<string, int>, documents: int, amount: float, reversed: int, reconciliations: int}>, totals: array{counts: array<string, int>, documents: int, amount: float, reversed: int, reconciliations: int}}
     */
    public function build(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->endOfDay();
        $blank = fn () => ['counts' => array_fill_keys(array_keys($this->documentTypes()), 0), 'documents' => 0, 'amount' => 0.0, 'reversed' => 0, 'reconciliations' => 0];
        $rows = [];

        foreach ($this->journals->activityByPoster($from, $to) as $group) {
            $key = $group->posted_by === null ? self::SYSTEM : (string) $group->posted_by;
            $rows[$key] ??= $blank();
            $documents = (int) $group->documents;
            $type = $group->voucher_type ?? 'journal';

            $rows[$key]['counts'][$type] = ($rows[$key]['counts'][$type] ?? 0) + $documents;
            $rows[$key]['documents'] += $documents;
            $rows[$key]['amount'] += (float) $group->amount;

            if ($group->status === Journal::STATUS_REVERSED) {
                $rows[$key]['reversed'] += $documents;
            }
        }

        $reconciliations = BankReconciliation::query()
            ->where('status', BankReconciliation::STATUS_COMPLETED)
            ->whereBetween('completed_at', [$from, $to])
            ->whereNotNull('completed_by')
            ->groupBy('completed_by')
            ->selectRaw('completed_by, COUNT(*) as total')
            ->pluck('total', 'completed_by');

        foreach ($reconciliations as $userId => $total) {
            $rows[(string) $userId] ??= $blank();
            $rows[(string) $userId]['reconciliations'] = (int) $total;
        }

        $names = User::query()
            ->withoutGlobalScope('tenant')
            ->whereIn('id', array_filter(array_keys($rows), fn ($key) => $key !== self::SYSTEM))
            ->pluck('name', 'id');

        $result = [];
        foreach ($rows as $key => $row) {
            $result[] = ['key' => (string) $key, 'name' => $key === self::SYSTEM ? 'System (auto-posted)' : ($names[$key] ?? "User #{$key}"), 'amount' => round($row['amount'], 2)] + $row;
        }

        usort($result, fn (array $a, array $b) => [$a['key'] === self::SYSTEM, -$a['documents']] <=> [$b['key'] === self::SYSTEM, -$b['documents']]);

        $totals = $blank();
        foreach ($result as $row) {
            foreach ($row['counts'] as $type => $count) {
                $totals['counts'][$type] += $count;
            }
            $totals['documents'] += $row['documents'];
            $totals['amount'] += $row['amount'];
            $totals['reversed'] += $row['reversed'];
            $totals['reconciliations'] += $row['reconciliations'];
        }
        $totals['amount'] = round($totals['amount'], 2);

        return ['rows' => $result, 'totals' => $totals];
    }
}
