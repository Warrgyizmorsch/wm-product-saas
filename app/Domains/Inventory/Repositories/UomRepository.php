<?php

namespace App\Domains\Inventory\Repositories;

use App\Domains\Inventory\Models\Uom;
use Illuminate\Database\Eloquent\Collection;

class UomRepository
{
    public function getAll(): Collection
    {
        return Uom::query()->orderBy('name')->get();
    }

    public function find(int $id): ?Uom
    {
        return Uom::find($id);
    }

    public function create(array $data): Uom
    {
        return Uom::create($data);
    }
}
