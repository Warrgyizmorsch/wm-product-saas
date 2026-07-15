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
        return Quotation::query()->with(['lead', 'salesPerson', 'items'])->find($id);
    }

    public function count(): int
    {
        return Quotation::query()->count();
    }

    public function latest(): \Illuminate\Database\Eloquent\Collection
    {
        return Quotation::query()
            ->with(['lead', 'salesPerson'])
            ->where('is_current', true)
            ->where('status', '!=', 'Draft')
            ->latest()
            ->get();
    }
}
