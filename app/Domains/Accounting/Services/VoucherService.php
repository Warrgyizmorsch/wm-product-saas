<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\Journal;
use App\Domains\Accounting\Repositories\VoucherDetailRepositoryInterface;
use App\Domains\Accounting\Support\VoucherType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class VoucherService
{
    public function __construct(
        private readonly JournalService $journals,
        private readonly VoucherDetailRepositoryInterface $voucherDetails,
    ) {
    }

    /**
     * Post a voucher (Payment, Receipt, Contra, Credit Note, or Debit Note) as a
     * 2-line balanced journal, reusing JournalService::post() for all balance
     * and period-lock validation, then attaching voucher-specific metadata.
     *
     * @param array<int, array{chart_of_account_id: int, debit?: float, credit?: float, description?: string}> $lines
     * @param array{
     *     journal_date?: string|\DateTimeInterface,
     *     memo?: string,
     *     party_type?: string,
     *     party_id?: int,
     *     party_name?: string,
     *     payment_method?: string,
     *     reference_no?: string,
     *     posted_by?: int,
     *     through_approval?: bool,
     * } $meta
     *
     * through_approval: true for vouchers a person keys in on the voucher
     * screens — they go through JournalService::submit() and may wait for
     * maker-checker approval. Vouchers posted by other flows (e.g. bank
     * reconciliation) leave it false and post immediately.
     */
    public function post(string $voucherType, array $lines, array $meta = []): Journal
    {
        if (!VoucherType::isValid($voucherType)) {
            throw new InvalidArgumentException("Unknown voucher type: {$voucherType}");
        }

        // tenant/company/branch and reference_* are optional: a voucher posted
        // from another screen (e.g. bank reconciliation) passes them so the
        // voucher lands in the right entity and links back to its source.
        $journalMeta = array_filter([
            'tenant_id' => $meta['tenant_id'] ?? null,
            'company_id' => $meta['company_id'] ?? null,
            'branch_id' => $meta['branch_id'] ?? null,
            'journal_date' => $meta['journal_date'] ?? now(),
            'source' => Journal::SOURCE_MANUAL,
            'voucher_type' => $voucherType,
            'journal_number_prefix' => VoucherType::prefix($voucherType),
            'reference_type' => $meta['reference_type'] ?? null,
            'reference_id' => $meta['reference_id'] ?? null,
            'memo' => $meta['memo'] ?? null,
            'posted_by' => $meta['posted_by'] ?? null,
        ], fn ($value) => $value !== null);

        $journal = ($meta['through_approval'] ?? false)
            ? $this->journals->submit($lines, $journalMeta)
            : $this->journals->post($lines, $journalMeta);

        $this->voucherDetails->create([
            'tenant_id' => $journal->tenant_id,
            'journal_id' => $journal->id,
            'voucher_type' => $voucherType,
            'party_type' => $meta['party_type'] ?? null,
            'party_id' => $meta['party_id'] ?? null,
            'party_name' => $meta['party_name'] ?? null,
            'payment_method' => $meta['payment_method'] ?? null,
            'reference_no' => $meta['reference_no'] ?? null,
        ]);

        return $journal->load('voucherDetail');
    }

    public function paginate(string $voucherType, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        return $this->journals->paginate(array_merge($filters, ['voucher_type' => $voucherType]), $perPage);
    }

    public function find(string $voucherType, int $journalId): ?Journal
    {
        $journal = $this->journals->find($journalId);

        if ($journal === null || $journal->voucher_type !== $voucherType) {
            return null;
        }

        return $journal->load('voucherDetail');
    }
}
