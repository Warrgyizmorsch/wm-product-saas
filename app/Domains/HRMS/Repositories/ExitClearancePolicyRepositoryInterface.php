<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\ExitClearanceTemplate;
use Illuminate\Http\Request;

interface ExitClearancePolicyRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeTemplate(array $validated, Request $request): int;

    public function updateTemplate(ExitClearanceTemplate $template, array $validated, Request $request): bool;

    public function deleteTemplate(ExitClearanceTemplate $template): bool;

    public function deleteCategory(string $categoryKey, ?int $companyId): int;

    public function resetTemplatesToDefaults(?int $companyId): void;
}
