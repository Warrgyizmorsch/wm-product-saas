<?php

namespace App\Domains\CRM\Repositories;

use App\Domains\CRM\Models\Customer;

class CustomerRepository
{
    public function create(array $data): Customer
    {
        return Customer::query()->create($data);
    }

    public function count(): int
    {
        return Customer::query()->where('status', 'active')->count();
    }

    public function activeCount(): int
    {
        return Customer::query()
            ->where('status', 'active')
            ->count();
    }

    public function latest(): \Illuminate\Database\Eloquent\Collection
    {
        return Customer::query()->latest()->get();
    }

    public function latestActive(): \Illuminate\Database\Eloquent\Collection
    {
        return Customer::query()->where('status', 'active')->latest()->get();
    }
}
