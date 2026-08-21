<?php

namespace App\Domains\Accounting\Repositories;

use App\Domains\Accounting\Models\VoucherDetail;

interface VoucherDetailRepositoryInterface
{
    public function create(array $data): VoucherDetail;

    public function findByJournalId(int $journalId): ?VoucherDetail;
}
