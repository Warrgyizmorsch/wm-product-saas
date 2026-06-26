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
        return Customer::query()->count();
    }

    public function activeCount(): int
    {
        return Customer::query()
            ->where('status', 'active')
            ->count();
    }
}
