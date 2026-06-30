<?php

namespace App\Domains\CRM\Services;

use App\Domains\CRM\Models\Customer;
use App\Domains\CRM\Repositories\CustomerRepository;

class CustomerService
{
    public function __construct(
        private readonly CustomerRepository $customers,
    ) {
    }

    public function summary(): array
    {
        return [
            'total' => $this->customers->count(),
            'active' => $this->customers->activeCount(),
        ];
    }

    public function create(array $data): Customer
    {
        return $this->customers->create($data);
    }

    public function latest(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->customers->latest();
    }

    public function latestActive(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->customers->latestActive();
    }
}
