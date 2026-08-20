<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\VoucherDetail;

class VoucherDetailRepository implements VoucherDetailRepositoryInterface
{
    public function create(array $data): VoucherDetail
    {
        return VoucherDetail::create($data);
    }

    public function findByJournalId(int $journalId): ?VoucherDetail
    {
        return VoucherDetail::where('journal_id', $journalId)->first();
    }
}
