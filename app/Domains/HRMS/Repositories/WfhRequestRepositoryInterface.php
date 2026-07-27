<?php

namespace App\Domains\HRMS\Repositories;

use App\Domains\HRMS\Models\WfhRequest;
use Illuminate\Http\Request;

interface WfhRequestRepositoryInterface
{
    public function getIndexData(array $inputs): array;

    public function storeWfhRequest(array $validated, Request $request): WfhRequest;

    public function updateStatus(WfhRequest $wfhRequest, array $validated, Request $request): bool;
}
