<?php

namespace App\Domains\Accounting\Services\Tds;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TdsDeduction;
use App\Domains\Accounting\Models\TdsSection;

/**
 * Writes the TDS register row for a posted document. Idempotent on
 * (source, section): posting the same bill again updates its row.
 */
class TdsDeductionRecorder
{
    /**
     * @param array{
     *     tenant_id: int, company_id?: ?int, branch_id?: ?int, tds_section_id: int,
     *     party_type?: string, party_id: int, party_name?: ?string, pan?: ?string, deductee_type?: ?string,
     *     source_type: string, source_id: int, source_number?: ?string, journal_id?: ?int,
     *     deduction_date: \DateTimeInterface|string, base_amount: float, catchup_base?: float,
     *     rate: float, tds_amount: float, reason?: string
     * } $data
     */
    public function record(array $data): TdsDeduction
    {
        $date = $data['deduction_date'];

        return TdsDeduction::query()->withoutGlobalScopes()->updateOrCreate(
            [
                'tenant_id' => $data['tenant_id'],
                'source_type' => $data['source_type'],
                'source_id' => $data['source_id'],
                'tds_section_id' => $data['tds_section_id'],
                'party_type' => $data['party_type'] ?? JournalEntry::PARTY_VENDOR,
                'party_id' => $data['party_id'],
            ],
            [
                'company_id' => $data['company_id'] ?? null,
                'branch_id' => $data['branch_id'] ?? null,
                'party_name' => $data['party_name'] ?? null,
                'pan' => filled($data['pan'] ?? null) ? strtoupper((string) $data['pan']) : null,
                'deductee_type' => $data['deductee_type'] ?? null,
                'source_number' => $data['source_number'] ?? null,
                'journal_id' => $data['journal_id'] ?? null,
                'deduction_date' => $date,
                'base_amount' => round((float) $data['base_amount'], 2),
                'catchup_base' => round((float) ($data['catchup_base'] ?? 0), 2),
                'rate' => (float) $data['rate'],
                'tds_amount' => round((float) $data['tds_amount'], 2),
                'reason' => $data['reason'] ?? TdsDeduction::REASON_NORMAL,
                'fiscal_year' => TdsDeduction::fiscalYearFor($date),
                'quarter' => TdsDeduction::quarterFor($date),
                'is_void' => false,
            ],
        );
    }

    /**
     * TDS booked by hand — a journal or payment voucher that credits a section
     * ledger against a vendor (rent or fees entered without a vendor bill).
     * Documents with their own TDS posting (vendor bills, challans) are skipped.
     */
    public function captureManualJournal(\App\Domains\Accounting\Models\Journal $journal): void
    {
        if (in_array($journal->reference_type, [TdsDeduction::SOURCE_VENDOR_BILL, 'tds_challan', TdsDeduction::SOURCE_PAYROLL_RUN], true)) {
            return;
        }

        // Salary TDS comes only from payroll runs, never from a hand-made entry.
        $sections = TdsSection::query()->withoutGlobalScopes()
            ->where('tenant_id', $journal->tenant_id)->whereNotNull('payable_account_id')
            ->where('code', '!=', TdsSection::SALARY_CODE)
            ->get()->keyBy('payable_account_id');

        if ($sections->isEmpty()) {
            return;
        }

        foreach ($journal->entries()->get() as $entry) {
            $section = $sections->get($entry->chart_of_account_id);

            if ($section !== null) {
                $this->recordJournalLine((int) $journal->tenant_id, (int) $journal->id, (string) $journal->journal_number, $section, [
                    'party_type' => $entry->party_type,
                    'party_id' => $entry->party_id,
                    'credit' => $entry->credit,
                    'date' => $journal->journal_date,
                ]);
            }
        }
    }

    public function recordJournalLine(int $tenantId, int $journalId, string $journalNumber, TdsSection $section, array $line): ?TdsDeduction
    {
        $vendor = ($line['party_type'] ?? null) === JournalEntry::PARTY_VENDOR && ! empty($line['party_id'])
            ? \App\Domains\Inventory\Models\Vendor::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->find($line['party_id'])
            : null;
        $amount = round((float) ($line['credit'] ?? 0), 2);

        if ($vendor === null || $amount <= 0) {
            return null;
        }

        $base = (float) ($line['tds_base_amount'] ?? 0);
        $rate = $base > 0 ? round($amount / $base * 100, 3) : $section->rateFor($vendor->deductee_type);

        return $this->record([
            'tenant_id' => $tenantId,
            'tds_section_id' => $section->id,
            'party_id' => $vendor->id,
            'party_name' => $vendor->name,
            'pan' => $vendor->pan,
            'deductee_type' => $vendor->deductee_type,
            'source_type' => TdsDeduction::SOURCE_JOURNAL,
            'source_id' => $journalId,
            'source_number' => $journalNumber,
            'journal_id' => $journalId,
            'deduction_date' => $line['date'],
            'base_amount' => $base > 0 ? $base : round($amount * 100 / max($rate, 0.001), 2),
            'rate' => $rate,
            'tds_amount' => $amount,
            'reason' => blank($vendor->pan) ? TdsDeduction::REASON_NO_PAN : TdsDeduction::REASON_NORMAL,
        ]);
    }
}
