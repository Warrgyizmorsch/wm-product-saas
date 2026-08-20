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
     * } $meta
     */
    public function post(string $voucherType, array $lines, array $meta = []): Journal
    {
        if (!VoucherType::isValid($voucherType)) {
            throw new InvalidArgumentException("Unknown voucher type: {$voucherType}");
        }

        $journal = $this->journals->post($lines, [
            'journal_date' => $meta['journal_date'] ?? now(),
            'source' => Journal::SOURCE_MANUAL,
            'voucher_type' => $voucherType,
            'journal_number_prefix' => VoucherType::prefix($voucherType),
            'memo' => $meta['memo'] ?? null,
            'posted_by' => $meta['posted_by'] ?? null,
        ]);

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
