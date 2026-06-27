<?php

namespace App\Domains\CRM\Repositories;

use App\Domains\CRM\Models\Quotation;

class QuotationRepository
{
    public function create(array $data): Quotation
    {
        return Quotation::query()->create($data);
    }

    public function find(int $id): ?Quotation
    {
        return Quotation::query()->with(['customer', 'salesPerson', 'items'])->find($id);
    }

    public function count(): int
    {
        return Quotation::query()->count();
    }

    public function latest(): \Illuminate\Database\Eloquent\Collection
    {
        return Quotation::query()->with(['customer', 'salesPerson'])->latest()->get();
    }
}
