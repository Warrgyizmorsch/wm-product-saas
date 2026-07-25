<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\SalaryComponent;
use App\Domains\HRMS\Models\SalaryStructure;
use Illuminate\Http\Request;

interface SalaryStructureRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeStructure(array $validated): SalaryStructure;

    public function updateStructure(SalaryStructure $salaryStructure, array $validated): bool;

    public function destroyStructure(SalaryStructure $salaryStructure): bool;

    public function storeComponent(array $validated): SalaryComponent;

    public function updateComponent(SalaryComponent $salaryComponent, array $validated): bool;

    public function destroyComponent(SalaryComponent $salaryComponent): bool;
}
